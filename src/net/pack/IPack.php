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
    function encode($data);

    function decode($data);
}
