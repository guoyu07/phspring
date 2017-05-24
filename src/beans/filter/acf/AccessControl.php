<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\filter\acf;

use phspring\beans\filter\FilterInput;

/**
 * AccessControl provides simple access control based on a set of rules.
 *
 * ```php
 * public function filters()
 * {
 *     return [
 *         'access' => [
 *             'class' => phspring\beans\filter\acf\AccessControl::class,
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
    }

    /**
     * This action is invoked right before an action is to be executed (after all possible filters.)
     * You may override this action to do last-minute preparation for the action.
     * @param string $controller the controller.
     * @param string $action the action.
     * @param FilterInput $request the request object.
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction(FilterInput $input)
    {
        $this->initRules();

        $action = $input->action;
        if (!$this->isActive($action)) {
            return true;
        }

        /* @var $rule AccessRule */
        foreach ($this->rules as $rule) {
            if ($allow = $rule->allows($this->user, $input)) {
                return true;
            } elseif ($allow === false) {
                if (isset($rule->denyCallback)) {
                    call_user_func($rule->denyCallback, $this->user, $input);
                } elseif ($this->denyCallback !== null) {
                    call_user_func($this->denyCallback, $this->user, $input);
                } else {
                    $this->denyAccess($this->user, $input);
                }
                return false;
            }
        }
        if ($this->denyCallback !== null) {
            call_user_func($this->denyCallback, null, $input);
        } else {
            $this->denyAccess($this->user, $input);
        }

        return false;
    }

    /**
     * init rules
     */
    protected function initRules()
    {
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
     * Denies the access of the user.
     * @param AccessUser $user the current user
     * @param FilterInput $input input parameters
     * @throws ForbiddenHttpException if the user is already logged in.
     */
    protected function denyAccess($user, FilterInput $input)
    {
        if ($user->getIsGuest()) {
            $user->loginRequired();
        } else {
            throw new \Exception('You are not allowed to perform this action.');
        }
    }
}
