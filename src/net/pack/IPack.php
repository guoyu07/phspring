<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\pack;

/**
 * Interface IPack
 * @package phspring\net\pack
 */
interface IPack
{
    /**
     * @param $data
     * @return mixed
     */
    function encode($data);

    /**
     * @param $data
     * @return mixed
     */
    function decode($data);
}
