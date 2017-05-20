<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core\aop;

use phspring\context\Ac;
use phspring\context\Context;
use phspring\core\PoolBean;
use phspring\core\Pool;

/**
 * Class AopFactory
 * @package phspring\core\aop
 */
class AopFactory
{
    /**
     * @var array
     */
    protected static $reflections = [];

    /**
     * get object pool
     * @param Pool $pool
     * @param Context $context
     * @return Aop
     */
    public static function getPool(Pool $pool, Context $context)
    {
        $aopPool = new Aop($pool);
        $gcConf = self::getPoolBeanGcConf();

        $aopPool->register('onBefore', function ($method, $args) use ($context, $gcConf) {
            if ($method === 'recover') {
                if (method_exists($args[0], 'scavenger')) {
                    $args[0]->scavenger();
                }
                $class = get_class($args[0]);
                if (!empty(self::$reflections[$class]) && method_exists($args[0], 'resetProperties')) {
                    $args[0]->resetProperties($args[0], self::$reflections[$class]);
                }
                if ($gcConf['enable']) {
                    $gc = $args[0]->getGc();
                    if (($gcConf['expire'] != 0 && ($gc['time'] + $gcConf['expire']) < time()) ||
                        ($gcConf['count'] != -1 && $gc['count'] > $gcConf['count'])
                    ) {
                        $params['result'] = false;
                        //$args[0] = null;
                        $args = null;
                    }
                }
            }
            $params['method'] = $method;
            $params['args'] = $args;

            return $params;
        });

        $aopPool->register('onAfter', function ($method, $args, $result) use ($context) {
            if ($method === 'get' && is_object($result) && $result instanceof PoolBean) {
                /* @var $result PoolBean */
                $result->incGcCount();
                $context->recoverableBeans[] = $result;
                $result->setContext($context);
                $class = get_class($result);
                if (!isset(self::$reflections[$class])) {
                    $reflection = new \ReflectionClass($class);
                    self::$reflections[$class] = array_diff_key($reflection->getDefaultProperties(),
                        $reflection->getStaticProperties());
                }
            }
            $params['method'] = $method;
            $params['args'] = $args;
            $params['result'] = $result;

            return $params;
        });


        return $aopPool;
    }

    /**
     * Get bean pool gc config
     * @return array
     */
    private static function getPoolBeanGcConf()
    {
        $conf = Ac::config()->get('PoolBean.gc', []);
        $conf['enable'] = $conf['enable'] ?? false;
        $conf['expire'] = $conf['maxExpireTime'] ?? 0;
        $conf['count'] = $conf['maxUseCount'] ?? -1;

        return $conf;
    }
}
