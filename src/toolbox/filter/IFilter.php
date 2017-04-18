<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\filter;

/**
 * Interface IFilter
 * @package PG\Filter
 */
interface IFilter
{
    /**
     * 在执行 method 前调用
     * @param Request $request
     * @return bool
     */
    function beforeMethod(Request $request);

    /**
     * filter fail.
     * @return mixed
     */
    function denyCallback();
}
