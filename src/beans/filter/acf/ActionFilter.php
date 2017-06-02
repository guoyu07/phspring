<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\filter\acf;

/**
 * Class ActionFilter
 * @package phspring\beans\filter\acf
 */
class ActionFilter
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
     * @param $action
     * @return bool
     */
    protected function isActive($action)
    {
        if (empty($this->only)) {
            $onlyMatch = true;
        } else {
            $onlyMatch = false;
            foreach ($this->only as $pattern) {
                if (fnmatch($pattern, $action)) {
                    $onlyMatch = true;
                    break;
                }
            }
        }

        $exceptMatch = false;
        foreach ($this->except as $pattern) {
            if (fnmatch($pattern, $action)) {
                $exceptMatch = true;
                break;
            }
        }

        return !$exceptMatch && $onlyMatch;
    }
}
