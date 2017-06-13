<?php
/**
 * description
 *
 * @author lijiaxu<jiaxu.li@zerotech.com>
 * @date 2017/3/23
 */

namespace MpaiApi\Logic;


class FlytimeLogic
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
        $className = __CLASS__;

        if (!isset(self::$_singletonObject [$className]) || !self::$_singletonObject [$className]) {
            self::$_singletonObject [$className] = new self ();
        }

        return self::$_singletonObject [$className];
    }

    public function getFlyTimeSortData($type)
    {
        $sortList = \YClient\Text::inst('ZeroMpai')->setClass('Flytime')->getFlyTimeSortData($type);
        foreach ($sortList as &$item) {//添加用户头像
            $item['photopath'] = MpaiLogic::instance()->getImgUrl($item['uid'], 1, 1);
        }
        return $sortList;
    }

    public function getFlyDistanceSortData($type)
    {
        $sortList = \YClient\Text::inst('ZeroMpai')->setClass('Flytime')->getFlyDistanceSortData($type);
        foreach ($sortList as &$item) {//添加用户头像
            $item['photopath'] = MpaiLogic::instance()->getImgUrl($item['uid'], 1, 1);
        }
        return $sortList;
    }

    public function getUserFlyList($uid, $maxTime, $limit)
    {
        return \YClient\Text::inst('ZeroMpai')->setClass('Flytime')->getUserFlyList($uid, $maxTime, $limit);
    }

    /**
     * 获取当前用户的飞行时长和时长排行值
     * @param $uid
     * @param $type 0 all 1 30d
     * @return mixed
     */
    public function getUserTimeSort($uid, $type)
    {
        return \YClient\Text::inst('ZeroMpai')->setClass('Flytime')->getUserTimeSort($uid, $type);
    }

    /**
     * 获取当前用户的飞行里程和里程排行值
     * @param $uid
     * @param $type 0 all 1 30d
     * @return mixed
     */
    public function getUserDistanceSort($uid, $type)
    {
        return \YClient\Text::inst('ZeroMpai')->setClass('Flytime')->getUserDistanceSort($uid, $type);
    }
}