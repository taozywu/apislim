<?php
/**
 * AppsLogic
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/11/01
 */

namespace MpaiApi\Logic;

/**
 * 处理逻辑模型
 */
class AppsLogic
{

    /**
     * 单例
     * @var type
     */
    private static $_singletonObject = null;

    private function __construct()
    {
    }

    /**
     * 实例化
     * @return AdminModel
     */
    public static function instance()
    {
        $className = __CLASS__ ;

        if( !isset( self::$_singletonObject [ $className ] ) || !self::$_singletonObject [ $className ] )
        {
            self::$_singletonObject [ $className ] = new self () ;
        }

        return self::$_singletonObject [ $className ] ;
    }

    
}
