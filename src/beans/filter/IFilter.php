<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\filter;

/**
 * Interface IFilter
 * @package phspring\beans\filter
 */
interface IFilter
{
    /**
     * 在执行 action 前调用
     * @param FilterInput $input
     * @return bool
     */
    function beforeAction(FilterInput $input);

    /**
     * filter fail.
     * @return mixed
     */
    function denyCallback();
}
