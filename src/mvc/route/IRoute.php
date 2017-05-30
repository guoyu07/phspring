<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc\route;

use phspring\mvc\HttpInput;

/**
 * Interface IRoute
 * @package phspring\mvc\route
 */
interface IRoute
{
    /**
     * @return string
     */
    public function getModuleName();

    /**
     * @return string
     */
    public function getControllerName();

    /**
     * @return string
     */
    public function getActionName();

    /**
     * @return array
     */
    public function getParams();

    /**
     * parse request
     */
    public function parseRequest($data);

    /**
     * @param HttpInput $httpInput
     * @return mixed
     */
    public function parseHttpRequest(HttpInput $httpInput);
}
