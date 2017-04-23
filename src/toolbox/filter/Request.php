<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\filter;

use phspring\core\Bean;

class Request extends Bean
{
    /**
     * @var string 当前访问请求的 controller 名称
     */
    public $controller;
    /**
     * @var string 当前访问请求的 controller 名称
     */
    public $method;
    /**
     * @var array 当前访问请求的所有 post 参数
     */
    public $post = null;
    /**
     * @var array 当前访问请求的所有 get 参数
     */
    public $get = null;
    /**
     * @var array 当前访问请求的所有 get & post 信息，等同于 $_REQUEST
     */
    public $query = null;
    /**
     * @var array 当前访问请求的所有 header 信息
     */
    public $headers = null;
    /**
     * @var array 当前访问请求的所有 server(即 $_SERVER) 信息
     */
    public $servers = null;
    /**
     * @var array 当前访问请求的所有 cookie 信息
     */
    public $cookies = null;
    /**
     * @var string 当前用户ip
     */
    public $ip = null;
    /**
     * @var null 扩展参数，可灵活放入数据
     */
    public $ext = null;

    public function init($controller, $method)
    {
        $this->controller = $controller;
        $this->method = $method;
    }

    /**
     * 对象回收时调用.
     */
    public function destroy()
    {
        $this->controller = null;
        $this->method = null;
        $this->post = null;
        $this->get = null;
        $this->request = null;
        $this->headers = null;
        $this->servers = null;
        $this->cookies = null;
        $this->ip = null;
        $this->ext = null;
    }
}
