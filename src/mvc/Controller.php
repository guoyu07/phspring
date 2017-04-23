<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\toolbox\helper\JsonHelper;

/**
 * Class Controller
 * @package phspring\mvc
 */
class Controller extends Base
{
    /**
     * @var string
     */
    public $layout = 'main';
    /**
     * @var View
     */
    private $_view;

    /**
     * get view
     */
    public function setView($view)
    {
        $this->_view = $view;
    }

    /**
     * render html page.
     * @param $view
     * @param array $params
     * @param bool $partial
     * @return mixed
     */
    public function render($view, $params = [], $partial = false)
    {
        $content = $this->_view->render($view, $params, $partial);
        return $content;
    }

    /**
     * output json
     */
    public function outputJson($data, $status = 200)
    {
        $this->context->log->pushLog('status', $status);
        return JsonHelper::encode($data);
    }

    /**
     * run method
     */
    public function runMethod()
    {

    }

    /**
     * destory
     */
    public function destory()
    {
        //parent::destory();
    }
}
