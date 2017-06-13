<?php
/**
 * 入口文件.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */
// 时区
date_default_timezone_set('Asia/Shanghai');

// 跟目录
define('ROOT',dirname(__DIR__));

// 加载模块
define("SITE", "MpaiApi");

// 全局函数
require_once ROOT."/Common/function.php";

// 加载自动加载类文件
require ROOT.'/Library/Rest.php';

$rest = new Rest;
$rest ->run();
