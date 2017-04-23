<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

/**
 * Class View
 * @package phspring\mvc
 */
class View extends Base
{
    /**
     * @param string $view
     * @param array $params
     * @param bool $partial
     */
    public function render($view, $params = [], $partial = false)
    {
        ob_start();
        ob_implicit_flush(false);
        extract($params, EXTR_OVERWRITE);
        require_once($this->getFile($view));

        return ob_get_clean();
    }

    /**
     * @param string $view
     */
    public function getFile($view)
    {
        return $view;
    }
}
