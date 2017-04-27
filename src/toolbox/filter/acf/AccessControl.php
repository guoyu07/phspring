<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\filter\acf;

use phspring\toolbox\filter\Request;

/**
 * AccessControl provides simple access control based on a set of rules.
 *
 * ```php
 * public function filters()
 * {
 *     return [
 *         'access' => [
 *             'class' => phspring\toolbox\filter\acf\AccessControl::class,
 *             'only' => ['create', 'update'],
 *             'rules' => [
 *                 // deny all POST requests
 *                 [
 *                     'allow' => false,
 *                     'verbs' => ['POST']
 *                 ],
 *                 // allow authenticated users
 *                 [
 *                     'allow' => true,
 *                     'roles' => ['@'],
 *                 ],
 *                 // everything else is denied
 *             ],
 *         ],
 *     ];
 * }
 * ```
 */
class AccessControl extends ActionFilter
{
    /**
     * @var AccessUser|string
     */
    public $user = 'user';
    /**
     * @var
     */
    public $denyCallback;
    /**
     * @var array
     */
    public $ruleConfig = ['class' => 'AccessRule'];
    /**
     * @var array
     */
    public $rules = [];

    /**
     * Initializes the [[rules]] array by instantiating rule objects from configurations.
     */
    public function __construct()
    {
        $this->user = new AccessUser();
        foreach ($this->rules as $i => $rule) {
            if (is_array($rule)) {
                $this->rules[$i] = new AccessRule();
                $props = array_merge($this->ruleConfig, $rule);
                foreach ($props as $prop => $val) {
                    $this->rules[$i]->$prop = $val;
                }
            }
        }
    }

    /**
     * This method is invoked right before an method is to be executed (after all possible filters.)
     * You may override this method to do last-minute preparation for the method.
     * @param string $controller the controller.
     * @param string $method the method.
     * @param Request $request the request object.
     * @return bool whether the method should continue to be executed.
     */
    public function beforeMethod(Request $request)
    {
        $method = $request->method;

        // 判断配置中设置的 only/except 属性中的 [method,...] 是否应用于当前 method.
        if (!$this->isActive($method)) {
            return true; // 当前 method 未被激活直接返回验证成功.
        }

        $user = $this->user;
        /* @var $rule AccessRule */
        foreach ($this->rules as $rule) {
            if ($allow = $rule->allows($user, $request)) {
                return true;
            } elseif ($allow === false) {
                if (isset($rule->denyCallback)) {
                    call_user_func($rule->denyCallback, $rule, $method);
                } elseif ($this->denyCallback !== null) {
                    call_user_func($this->denyCallback, $rule, $method);
                } else {
                    $this->denyAccess($user, $request);
                }
                return false;
            }
        }
        if ($this->denyCallback !== null) {
            call_user_func($this->denyCallback, null, $method);
        } else {
            $this->denyAccess($user, $request);
        }

        return false;
    }

    /**
     * Denies the access of the user.
     * The default implementation will redirect the user to the login page if he is a guest;
     * if the user is already logged, a 403 HTTP exception will be thrown.
     * @param AccessUser $user the current user
     * @throws ForbiddenHttpException if the user is already logged in.
     */
    protected function denyAccess($user, Request $request)
    {
        if ($user->getIsGuest()) {
            $user->loginRequired();
        } else {
            throw new \Exception('You are not allowed to perform this method.');
        }
    }
}
