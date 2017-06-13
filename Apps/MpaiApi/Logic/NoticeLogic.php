<?php

namespace MpaiApi\Logic;

/**
 * 处理逻辑模型
 */
class NoticeLogic
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

    /**
     * 评论回复转通知列表{此处后面还需要优化,尽量走事务机制}.
     * @author taozywu <tao.wu@zerotech.com>
     *
     * @param integer $qzid  圈子ID.
     * @param integer $uid   回复人ID.
     * @param integer $ruid  被回复人或者评论人ID.
     * @param integer $mpid  作品ID.
     * @param integer $rtype 1评论 2回复类型.
     * @param integer $types 消息类型.
     *
     * @return bool.
     */
    public function Data2Notice($qzid, $uid, $ruid, $mpid, $rtype=1, $types=2)
    {
        if (!$uid||!$ruid||!$mpid) {
            return false;
        }
        $ctime = time();
        $content = $uid . "@" . ($rtype==1 ? "__COMMENT__" : "__REPLY__");
        $data = array(
            "qzid" => $qzid,
            "uid" => $uid,
            "types" => $types,
            "creattime" => $ctime,
            "content" => $content,
            "mpid" => $mpid,
            "ruid" => $ruid,
        );
        $newid = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->addNews($data);
        if (!$newid) {
            return false;
        }
        $opera = array(
            "qzid" => $qzid,
            "uid" => $ruid,
            "newid" => $newid,
            "status" => 2,
            "create_time" => $ctime,
            "types" => $types,
            "issend" => $ruid > 0 ? 0 : 2,
        );
        $oid = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->addOpera($opera);
        if (!$oid) {
            return false;
        }
        // 往队列添加一条通知.
        if ($ruid > 0) \YClient\Text::inst("ZeroNotice")->setClass("Notice")->addNoticeQue($oid, $ruid, $newid);
        // 删除分类通知,在增加一条分类通知.
        $delTypeNotice = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->delTypeNotice($ruid, 2);
        if (!$delTypeNotice) {
            return false;
        }
        $typeNotice = array(
            "qzid" => $qzid,
            "types" => $types,
            "uid" => $ruid,
            "nid" => $newid,
            "content" => $content,
            "created" => $ctime,
        );
        $add =  \YClient\Text::inst("ZeroNotice")->setClass("Notice")->addNotice($typeNotice);
        unset($data, $opera, $typeNotice);
        return $add;
    }

    /**
     * [getNoticeData description]
     * @return [type] [description]
     */
    public function getNoticeData()
    {
        return \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getNoticeData();
    }

    /**
     * [saveNoticeStatus description]
     * @param  [type] $map [description]
     * @return [type]      [description]
     */
    public function saveNoticeStatus($map)
    {
        return \YClient\Text::inst("ZeroNotice")->setClass("Notice")->saveNoticeStatus($map);
    }

    /**
     * [getNoticeNum description]
     * @return [type] [description]
     */
    public function getNoticeNum()
    {
        return \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getNoticeNum();
    }

    /**
     * [getLangData description]
     * @param  [type] $map [description]
     * @return [type]      [description]
     */
    public function getLangData($map)
    {
        return \YClient\Text::inst("ZeroMpai")->setClass("User")->getLangData($map);
    }

    /**
     * [setCommentNotice description]
     * @param [type] $ruid [description]
     * @param [type] $mpid [description]
     */
    public function setCommentNotice($ruid,$mpid)
    {
        if(!$ruid||!$mpid){
            return false;
        }

        $ridArr = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getRidArr($ruid,$mpid);
        if(!$ridArr){
            return false;
        }
        foreach ($ridArr as $k => $v) {
            $rarr[] = $v['id'];
        }

        $map['uid'] = $ruid;
        $map['rid'] = $rarr;

        $st = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->saveOperaStatus($map);

        if($st){
            return true;
        }else{
            return false;
        }
    }
}
