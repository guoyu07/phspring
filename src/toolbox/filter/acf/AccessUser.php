<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\filter\acf;

/**
 * Class AccessUser
 * @package phspring\toolbox\filter\acf
 */
class AccessUser
{
    /**
     * @var 用户id
     */
    public $id = null;

    /**
     * @var 用户昵称
     */
    public $name = null;

    /**
     * @return bool whether the current user is a guest.
     */
    public function getIsGuest()
    {
        return $this->id === null;
    }

    /**
     * 访问是否必须登录
     * @return bool
     */
    public function loginRequired()
    {
        return true;
    }
}
