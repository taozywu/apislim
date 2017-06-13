<?php
/**
 * 钩子.
 *
 * @author taozywu <tao.wu@zerotech.com>
 * @date 2016/10/20
 */

namespace Custom;

use Rest;

class Hook {

    /**
     * 开始前.
     *
     * @throws \Exception
     */
    public static function before()
    {
        // 检查路由
        \Custom\Route::instances()->checkUri();
    }

    /**
     * 开始路由前.
     *
     */
    public static function beforeRouter()
    {
        // 创建路由
        \Custom\Route::instances()->createController();
    }

    /**
     * 开始执行前.
     */
    public static function beforeDispatch()
    {
    }

    /**
     *
     * 执行后.
     */
    public static function afterDispatch()
    {
    }

    /**
     * 路由完成后.
     */
    public static function afterRouter()
    {
    }

    /**
     * 处理完成后.
     */
    public static function after()
    {
    }
}
