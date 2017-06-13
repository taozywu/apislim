<?php

namespace MpaiApi\Logic;

/**
 * 处理逻辑模型
 */
class UserLogic
{

    /**
     * 单例
     * @var type
     */
    private static $_singletonObject = null ;

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
     * [getNickName description]
     * #@todo
     * @return [type] [description]
     */
    public function getNickName()
    {
         return C("regDefaultUser");
    }

    /**
     * 获取个人首页
     * 
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function getHisIndex($mid,$uid)
    {
        if (!is_numeric($uid) || !is_numeric($mid)) {
            return 100000001;
        }
        $uList = $this->getUInfo($uid);
        $isme = $mid == $uid ? 1 : 0;
        $uData = array(
            "uid" => isset($uList['uid']) ? $uList['uid'] : 0,
            "rname" => isset($uList['nname']) ? $uList['nname'] : "",
            "avatar" => isset($uList['avatar']) ? $uList['avatar'] : "",
        );
        $result = array(
             "user" => $uData,
             "bg_url" => isset($uList['bg_url']) ? $uList['bg_url'] : "",
             "like_count" => \YClient\Text::inst("ZeroMpai")->setClass("User")->getHisLikeCount($uid),
             "fans_count" => \YClient\Text::inst("ZeroMpai")->setClass("User")->getHisFansCount($uid),
             "follow_count" => \YClient\Text::inst("ZeroMpai")->setClass("User")->getHisFollowedCount($uid),
             "bbs_url" => "",
             "is_owner" => $isme,
             "follow_state" => 0,
        );
        unset($uList);
        if (!$isme && $mid > 0) {
            $result['follow_state'] = \YClient\Text::inst("ZeroMpai")->setClass("User")->checkFollowed(
                $mid, \Common\Tool::OBJECT_TYPE_USER, $uid
            );
        }
        if ($isme) {
            $result['flight'] = (object) array();
            $result['fly_recordtext'] = $this->getMyFlyRecordNum($mid);
            $result['fly_ranktext'] = $this->getMyFlyRankNum($mid);
            $result['fav_num'] = $this->getMyFavNum($mid);
        }
        return $result;
    }

    /**
     * 获取我的飞行记录数.
     * 
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    private function getMyFlyRecordNum($uid)
    {
        $num = (int) \YClient\Text::inst("ZeroMpai")->setClass("Flytime")->getUserFlysTotal($uid);
        $num = $num > C("MyFlyRecordMaxNum") ? C("MyFlyRecordMaxNum")."+" : $num;
        return str_replace("__NUM__", $num, C("LANG.100110014"));
    }

    /**
     * 获取我的飞行排行数.
     * 
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    private function getMyFlyRankNum($uid)
    {
        $key = "ZERO_USER_DATA:$uid";
        $redis = \Common\Redis\CRedis::cache('default');
        $rank = $redis->zRevRank("ZERO_FLY_TIME_SORT_30d", $uid);
        if ($rank === false) {
            $rank = 0;
            goto output_rank;
        }
        $rank++;
        $rank = $rank > C("MyFlyRankMaxNum") ? C("MyFlyRankMaxNum")."+" : $rank;
        goto output_rank;

        output_rank:
        return str_replace("__RANK__", $rank, C("LANG.100110015"));
    }

    /**
     * 获取我的收藏数.
     * 
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    private function getMyFavNum($uid)
    {
        $num = (int) \YClient\Text::inst("ZeroMpai")->setClass("User")->getMyFavNum($uid);
        return $num > C("MyFavMaxNum") ? C("MyFavMaxNum")."+" : $num;
    }

    /**
     * 获取个人资料
     * 
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function getUInfo($uid)
    {
        if (!is_numeric($uid)) {
            return 100000001;
        }
        $uInfo = \YClient\Text::inst("ZeroMpai")->setClass("User")->getUInfoNew($uid);
        if (!$uInfo) {
            return null;
        }
        $result = array(
            "uid" => $uInfo['id'],
            "nname" => $uInfo['nickname'] ? dataEscape($uInfo['nickname']) : $this->getNickName(),
            "rname" => dataEscape($uInfo['real_name']),
            "sex" => $uInfo['sex'],
            "mobile" => !$uInfo['mb_phone'] || $uInfo['mb_phone'] == 0 ? "" : $uInfo['mb_phone'],
            "avatar" => $uInfo['photopath'],
            "bg_url" => $uInfo['bgpath'],
            "address" => dataEscape($uInfo['address']),
        );
        $this->dealUserInfo($uid, $result);
        unset($uInfo);
        return $result;
    }

    /**
     * 处理用户头像和背景图.
     * 
     * @param  [type] &$result [description]
     * @return [type]          [description]
     */
    private function dealUserInfo($uid, &$result)
    {
        $result['avatar'] = MpaiLogic::instance()->getImgUrl($uid, 1, 2);
        $result['bg_url'] = MpaiLogic::instance()->getImgUrl($uid, 5, 3);
    }

    /**
     * [getHisMpaiList description]
     * @param  [type]  $uid   [description]
     * @param  integer $maxId [description]
     * @param  integer $limit [description]
     * @return [type]         [description]
     */
    public function getHisMpaiList($uid, $maxId = 0, $limit = 20)
    {
        if (!is_numeric($uid)) {
            return 100000001;
        }
        $data = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getHisMpaiList($uid, $maxId, $limit);
        if (!$data) {
            return 100000002;
        }

        $result = array();
        $returnMax = 0;
        MpaiLogic::instance()->dealFoundData($data, $result, $returnMax);
        unset($data);
        return array(
            "list" => $result,
            "max_id" => $returnMax,
            "limit" => $limit,
        );
    }

    /**
     * addFollowed
     * @param [type] $uid  [description]
     * @param [type] $fuid [description]
     * @param [type] $flag [description]
     */
    public function addFollowed($uid, $fuid, $flag)
    {
        if (!is_numeric($uid) || !is_numeric($fuid)) {
            return 100000001;
        }
        if (!in_array($flag, array(1, 2))) {
            return 100000001;
        }
        $check = \YClient\Text::inst("ZeroMpai")->setClass("User")->checkFollowed(
            $uid, \Common\Tool::OBJECT_TYPE_USER, $fuid
        );
        // 关注 & 有数据
        if ($flag === 1 && $check > 0) {
            return 100000003;
        }
        // 取消 & 没数据
        if ($flag === 2 && $check < 1) {
            return 100000003;
        }
        $follow = 0;
        switch ($flag) {
            case 1:
                $follow = \YClient\Text::inst("ZeroMpai")->setClass("User")->addFollowed(
                    $uid, \Common\Tool::OBJECT_TYPE_USER, $fuid
                );
                break;
            case 2:
                $follow = \YClient\Text::inst("ZeroMpai")->setClass("User")->addDisFollowed(
                    $uid, \Common\Tool::OBJECT_TYPE_USER, $fuid
                );
                break;
        }
        # 如果是非0的情况
        return !$follow ? 100000003 : $follow;
    }

    /**
     * 编辑个人资料
     * 
     * @param  [type] $uid    [description]
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function editUInfo($uid, array $params)
    {
        if (!is_numeric($uid) || !$params) {
            return 100000001;
        }
        $edit = \YClient\Text::inst("ZeroMpai")->setClass("User")->editUInfo($uid,$params);
        return !$edit ? 100000003 : 1;
    }

    /**
     * [editUExtension description]
     * @param  [type] $uid    [description]
     * @param  [type] $params [description]
     * @return [type]         [description]
     */
    public function editUExtension($uid, $params)
    {
        return \YClient\Text::inst("ZeroMpai")->setClass("User")->editUExtension($uid,$params);
    }

    /**
     * [saveRedis description]
     * @return [type] [description]
     */
    public function saveRedis($uid, array $params)
    {
        $key = "ZERO_USER_DATA:$uid";
        $redis = \Common\Redis\CRedis::cache('default');
        foreach ($params as $pk => $v) {
            $redis->hSet($key, $pk, $v);
        }
        return true;
    }

    /**
     * 获取我的作品列表.
     * 
     * @param  [type]  $uid   [description]
     * @param  integer $limit [description]
     * @return [type]         [description]
     */
    public function getMyMpList($uid, $limit = 6)
    {
        $arr = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getMyMpList($uid, $limit);
        $result = array();
        MpaiLogic::instance()->dealFoundData($arr, $result);
        unset($arr);
        return $result;
    }

    /**
     * [setUserLang description]
     * @param [type] $data [description]
     */
    public function setUserLang($data){
        $st = \YClient\Text::inst("ZeroMpai")->setClass("User")->getUserLang($data);
        if(!$st){
            $map['uid'] = $data['uid'];
            $map['status'] = 1; 
            $del = \YClient\Text::inst("ZeroMpai")->setClass("User")->delUserLang($map);
            $id = \YClient\Text::inst("ZeroMpai")->setClass("User")->addUserLang($data);
        }
    }

    /**
     * 添加用户扩展字段数据.
     * 
     * @param array $params [description]
     */
    public function addUserExtension(array $params)
    {
        if (!$params) {
            return false;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("User")->addUserExtension($params);
    }

    /**
     * 获取用户等级和总时长.
     * 
     * @return [type] [description]
     */
    public function getUserLevel($uid)
    {
        $tTime = \YClient\Text::inst("ZeroMpai")->setClass("User")->getTotalTime($uid);
        if ($tTime < 1) {
            return array(
                "level" => 1,
                "totaltime" => $tTime,
            );
        }
        $level = $this->getLevelData($tTime);
        if ($tTime > 60*10) {
            $level = 4;
        }
        return array(
            "level" => $level,
            "totaltime" => $tTime,
        );
    }

    /**
     * 处理用户等级.
     * 
     * @param  [type] $totaltime [description]
     * @return [type]            [description]
     */
    protected function getLevelData($totaltime)
    {
        if (!$totaltime) {
            return 0;
        }
        $level = floor($totaltime/10/60);
        return $level;
    }

}
