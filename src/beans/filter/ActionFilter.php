<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\filter;

/**
 * Class ActionFilter
 * @package phspring\beans\filter\acf
 */
abstract class ActionFilter
{
    /**
     * @var array
     */
    public $include;
    /**
     * @var array
     */
    public $exclude = [];

    /**
     * @param FilterInput $input
     * @return bool
     */
    public function beforeAction(FilterInput $input)
    {
        if (!$this->isAllow($input->action)) {
            return true;
        }

        return $this->execute($input);
    }

    /**
     * deny callback
     */
    public function denyCallback()
    {

    }

    /**
     * @param FilterInput $input
     * @return bool
     */
    abstract protected function execute(FilterInput $input);

    /**
     * @param $action
     * @return bool
     */
    protected function isAllow($action)
    {
        if (empty($this->include)) {
            $includeMatch = true;
        } else {
            $includeMatch = false;
            foreach ($this->include as $pattern) {
                if (fnmatch($pattern, $action)) {
                    $includeMatch = true;
                    break;
                }
            }
        }
        if (empty($this->exclude)) {
            return $includeMatch;
        }

        $excludeMatch = false;
        foreach ($this->exclude as $pattern) {
            if (fnmatch($pattern, $action)) {
                $excludeMatch = true;
                break;
            }
        }

        return  $includeMatch && !$excludeMatch;
    }
}
