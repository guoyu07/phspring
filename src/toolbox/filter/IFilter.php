<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\filter;

/**
 * Interface IFilter
 * @package phspring\toolbox\filter
 */
interface IFilter
{
    /**
     * 在执行 method 前调用
     * @param FilterInput $input
     * @return bool
     */
    function beforeMethod(FilterInput $input);

    /**
     * filter fail.
     * @return mixed
     */
    function denyCallback();
}
