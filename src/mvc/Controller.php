<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

/**
 * Class Controller
 * @package phspring\mvc
 */
class Controller extends Base
{
    /**
     * @var string
     */
    public $mode = 'web';
    /**
     * @var string
     */
    public $requestType = 'http';
    /**
     * @var null
     */
    public $controllerName = null;
    /**
     * @var null
     */
    public $actionName = null;

    /**
     * @var View
     */
    private $_view;

    /**
     * @param string $controllerName
     * @param string $actionName
     */
    public function init($controllerName, $actionName)
    {
        $this->controllerName = $controllerName;
        $this->actionName = $actionName;
    }

    /**
     * run action
     */
    public function runAction($args = [])
    {
        $this->beforeAction();
        $result = call_user_func_array([$this, $this->actionName], $args);
        $this->afterAction($result);

        $this->response($result);
    }

    /**
     * before action
     */
    public function beforeAction()
    {
        // ...
    }

    /**
     * after action
     */
    public function afterAction($result)
    {
        // ...
    }

    /**
     * @param mixed $result
     */
    public function response(&$result)
    {
        if ($this->requestType == 'http') {
            $this->context->output->end($result);
        } else { // tcp
            $this->context->output->send($result);
        }
        $this->context->cleanup();
    }

    /**
     * @param \Throwable $e
     */
    public function onErrorHandler(\Throwable $e)
    {
        try {
            $errMsg = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            $errMsg .= ' Trace: ' . $e->getTraceAsString();
            if (!empty($e->getPrevious())) {
                $errMsg .= ' Previous trace: ' . $e->getPrevious()->getTraceAsString();
            }

            $this->context->log->error($errMsg . ' with code ' . $e->getCode());
            $this->outputJson(parent::$stdClass, $e->getMessage(), $e->getCode());
        } catch (\Throwable $ne) {
            echo 'Call Controller::onErrorHandler Error', PHP_EOL;
            echo 'Last Exception: ', $e->getTraceAsString(), PHP_EOL;
            echo 'Handle Exception: ', $ne->getTraceAsString(), PHP_EOL;
        }
        $errMsg = '';
        $this->response($errMsg);
    }

    /**
     * get View $view
     */
    public function setView(View $view)
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
        return $this->_view->render($view, $params, $partial);
    }

    /**
     * cleanup
     */
    public function cleanup()
    {
        parent::cleanup();
    }
}
