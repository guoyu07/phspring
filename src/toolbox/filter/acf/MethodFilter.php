<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\filter\acf;

/**
 * Class MethodFilter
 * @package phspring\toolbox\filter\acf
 */
class MethodFilter
{
    /**
     * @var array
     */
    public $only;
    /**
     * @var array
     */
    public $except = [];

    /**
     * @param $method
     * @return bool
     */
    protected function isActive($method)
    {
        if (empty($this->only)) {
            $onlyMatch = true;
        } else {
            $onlyMatch = false;
            foreach ($this->only as $pattern) {
                if (fnmatch($pattern, $method)) {
                    $onlyMatch = true;
                    break;
                }
            }
        }

        $exceptMatch = false;
        foreach ($this->except as $pattern) {
            if (fnmatch($pattern, $method)) {
                $exceptMatch = true;
                break;
            }
        }

        return !$exceptMatch && $onlyMatch;
    }
}
