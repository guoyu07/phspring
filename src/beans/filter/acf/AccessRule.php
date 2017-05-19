<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\filter\acf;

use phspring\beans\filter\FilterInput;

/**
 * Class AccessRule
 * @package phspring\beans\filter\acf
 */
class AccessRule
{
    /**
     * @var
     */
    public $allow;
    /**
     * @var
     */
    public $methods;
    /**
     * @var
     */
    public $controllers;
    /**
     * @var
     */
    public $roles;
    /**
     * @var array
     */
    public $ips;
    /**
     * @var array
     */
    public $verbs;
    /**
     * @var
     */
    public $matchCallback;
    /**
     * @var
     */
    public $denyCallback;

    /**
     * AccessRule constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $user
     * @param FilterInput $input
     * @return bool|null
     */
    public function allows(AccessUser $user, FilterInput $input)
    {
        if ($this->matchMethod($input->method)
            && $this->matchRole($user)
            && $this->matchIP($input->ip)
            && $this->matchVerb($input->verb)
            && $this->matchController($input->controller)
            && $this->matchCustom($input->method)
        ) {
            return $this->allow ? true : false;
        } else {
            return null;
        }
    }

    /**
     * @param $method
     * @return bool
     */
    protected function matchMethod($method)
    {
        return empty($this->methods) || in_array($method, $this->methods, true);
    }

    /**
     * @param $controller
     * @return bool
     */
    protected function matchController($controller)
    {
        return empty($this->controllers) || in_array($controller, $this->controllers, true);
    }

    /**
     * @param AccessUser $user
     * @return bool
     */
    protected function matchRole(AccessUser $user)
    {
        if (empty($this->roles)) {
            return true;
        }
        foreach ($this->roles as $role) {
            if ($role === '?') {
                if ($user->getIsGuest()) {
                    return true;
                }
            } elseif ($role === '@') {
                if (!$user->getIsGuest()) {
                    return true;
                }
            }
            // elseif ($user->can($role)) {
            //     return true;
            // }
        }

        return false;
    }

    /**
     * @param string $ip the IP address
     * @return bool whether the rule applies to the IP address
     */
    protected function matchIP($ip)
    {
        if (empty($this->ips)) {
            return true;
        }
        foreach ($this->ips as $rule) {
            if ($rule === '*' || $rule === $ip || (($pos = strpos($rule, '*')) !== false && !strncmp($ip, $rule,
                        $pos))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $verb the request method.
     * @return bool whether the rule applies to the request
     */
    protected function matchVerb($verb)
    {
        return empty($this->verbs) || in_array(strtoupper($verb), array_map('strtoupper', $this->verbs), true);
    }

    /**
     * @param string $method the action to be performed
     * @return bool whether the rule should be applied
     */
    protected function matchCustom($method)
    {
        return empty($this->matchCallback) || call_user_func($this->matchCallback, $this, $method);
    }
}
