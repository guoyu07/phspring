<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core\aop;

use phspring\context\Context;
use phspring\core\BeanPool;

/**
 * Class AopFactory
 * @package phspring\core\aop
 */
class AopFactory
{
    /**
     * get beanPool
     * @param BeanPool $pool
     * @param Context $context
     * @return Aop
     */
    public static function getBeanPool(BeanPool $pool, Context $context)
    {
        $aopPool = new Aop($pool);
        $aopPool->registerOnBefore(function ($method, $args) use ($context) {
            if ($method === 'recover') {
                if (method_exists($args[0], 'scavenger')) {
                    $args[0]->scavenger();
                }
                if (($args[0]->genTime + 7200) < time() || $args[0]->useCount > 10000) {
                    $data['result'] = false;
                    unset($args[0]);
                }
            }
            $data['method'] = $method;
            $data['arguments'] = $args;

            return $data;
        });

        $aopPool->registerOnAfter(function ($method, $args, $result) use ($context) {
            //取得对象后放入请求内部bucket
            if ($method === 'get' && is_object($result)) {
                //使用次数+1
                $result->useCount++;
                $context->recoverableBeans[] = $result;
                $result->context = $context;
            }
            $data['method'] = $method;
            $data['arguments'] = $args;
            $data['result'] = $result;

            return $data;
        });

        return $aopPool;
    }
}
