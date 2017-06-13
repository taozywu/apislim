<?php

namespace MpaiApi\Logic;

/**
 * 处理逻辑模型
 */
class MpaiLogic
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
     * 获取所有图片的处理.
     * 
     * @param  [type] $id         ID.
     * @param  [type] $flag       1-用户图片3-发布视频图片4-发布图片5-用户背景图6-流图
     * @param  [type] $flagNumber 图片分辨率
     * @return [type]             [description]
     */
    public function getImgUrl($id, $flag, $flagNumber = 0)
    {
        if (!$id) {
            return "";
        }
        // 处理https
        $ishttps = IS_HTTPS() ? "_1" : "";
        // 查找是否有缓存
        $key = "ZERO_MPAI_URL:{$id}_{$flag}_{$flagNumber}{$ishttps}";
        $redis = \Common\Redis\CRedis::cache('default');
        $url = $redis->get($key);
        if ($url) {
            #担心缓存导致https访问有问题？
            return $url;
        }

        // 获取url和url来源
        $url = $urlFrom = "";
        if ($flag == 1 || $flag == 5) {
            $this->getUserUrl($id, $flag, $url, $urlFrom);
        } else {
            $this->getMpaiUrl($id, $flag, $url, $urlFrom);
        }
        $ossConf = C("ALIOSS_CONFIG2");
        // 检测这个url存在且是http格式
        // 下面的处理主要是针对图片
        if ($url && strstr($url, "http://") !== false) {
            $path = strstr($url, "Uploads");
            $url = get_file_url3($ossConf['IMG']['BUCKET'], $path, C("urlExpire"));
            if ($url) {
                $redis->setex($key, C("urlExpire"), $url);
            }
            return $url;
        }

        $bucket = $path = "";
        $outNumber = getImgStyle($flagNumber);
        switch ($flag) {
            case 5:
            case 4:
            case 1:
                $bucket = $ossConf["IMG"]["BUCKET"];
                $path = $ossConf["IMG"]["OBJECT"] . "/" . $url. ".jpg";
                break;
            case 3:
                $bucket = $ossConf["VIDEO"]["OUT_BUCKET"];
                $path = $ossConf["VIDEO"]["OUT_OBJECT_IMG"] . "/" . $url . ".jpg";
                if ($urlFrom == 2) {
                    $bucket = C("VIDEO_OUT_BUCKET");
                    $path = $ossConf["VIDEO"]["OUT_OBJECT_IMG"] . "/" . $url . ".jpg";
                }
                break;
            case 6:
                $bucket = $ossConf["DRAG"]["BUCKET"];
                $path = $ossConf["DRAG"]["OBJECT"] . "/" . $url. ".jpg";
                break;
        }

        $path .= $flagNumber ? $outNumber : '';
        if (!$bucket || !$path) {
            return "";
        }

        $url = get_file_url3($bucket, $path, C("urlExpire"));
        if ($url) {
            $redis->setex($key, C("urlExpire"), $url);
        }
        return $url;
    }

    /**
     * 获取所有视频的处理
     * 
     * @param  [type] $id         [description]
     * @param  [type] $flag       2
     * @param  [type] $flagNumber 视频分辨率
     * @return [type]             [description]
     */
    public function getVideoUrl($id, $flag = 2, $flagNumber = 0)
    {
        if (!$id) {
            return "";
        }
        // 处理https
        $ishttps = IS_HTTPS() ? "_1" : "";
        // 查找是否有缓存
        $key = "ZERO_MPAI_URL:{$id}_{$flag}_{$flagNumber}{$ishttps}";
        $redis = \Common\Redis\CRedis::cache('default');
        $url = $redis->get($key);
        if ($url) {
            #担心缓存导致https访问有问题？
            return $url;
        }

        $this->getMpaiUrl($id, $flag, $url, $urlFrom);
        $ossConf = C("ALIOSS_CONFIG2");
        $bucket = $path = "";
        $outNumber = getVideoStyle($flagNumber);
        switch ($flag) {
            case 2:
                if ($urlFrom == 2) {
                    $bucket = C("VIDEO_OUT_BUCKET");
                    $path = $ossConf['VIDEO']['OUT_OBJECT_'.$outNumber] . "/" . $url . ".mp4";
                    break;
                }
                $bucket = $ossConf['VIDEO']['OUT_BUCKET'];
                $path = $ossConf['VIDEO']['OUT_OBJECT_'.$outNumber] . "/" . $url . ".mp4";
                break;
        }

        if (!$bucket || !$path) {
            return "";
        }

        $url = get_file_url3($bucket,  $path, C("urlExpire"));
        if ($url) {
            $redis->setex($key, C("urlExpire"), $url);
        }
        return $url;
    }

    /**
     * [getUserUrl description]
     * @param  [type] $id       [description]
     * @param  [type] $flag     [description]
     * @param  [type] &$url     [description]
     * @param  [type] &$urlFrom [description]
     * @return [type]           [description]
     */
    private function getUserUrl($id, $flag, &$url, &$urlFrom)
    {
        $uInfo = \YClient\Text::inst("ZeroMpai")->setClass("User")->getUInfo($id);
        if ($flag == 1) {
            $url = isset($uInfo['photopath']) ? $uInfo['photopath'] : "";
        } else if ($flag == 5) {
            $url = isset($uInfo['bgpath']) ? $uInfo['bgpath'] : "";
        }
        $urlFrom = 0;
        unset($uInfo);
    }

    /**
     * getMpaiUrl
     * @param  [type] $id       [description]
     * @param  [type] $flag     [description]
     * @param  [type] &$url     [description]
     * @param  [type] &$urlFrom [description]
     * @return [type]           [description]
     */
    private function getMpaiUrl($id, $flag, &$url, &$urlFrom)
    {
        $mInfo = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getMpaiInfo($id);
        $url = isset($mInfo['url']) ? $mInfo['url'] : "";
        // 针对流图进行特殊的处理 & 流图第一张图
        if ($flag == 6) {
            $url = substr($url, 2) . "000";
        }
        $urlFrom = isset($mInfo['from_step']) ? (int) $mInfo['from_step'] : 0;
        unset($mInfo);
    }

    /**
     * getMpaiInfo
     * @param  [type] $mpid [description]
     * @return [type]       [description]
     */
    public function getMpaiInfo($mpid)
    {
        if (!$mpid) {
            return 100000001;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getMpaiInfo($mpid);
    }

    /**
     * addReply
     */
    public function addReply(array $params)
    {
        if (!$params) {
            return 100000001;
        }
        $add = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addReply($params);
        return $add;
    }

    /**
     * addFavorite
     * @param [type] $uid  [description]
     * @param [type] $mpid [description]
     */
    public function addFavorite($uid, $mpid, $flag)
    {
        if (!is_numeric($uid) || !is_numeric($mpid)) {
            return 100000001;
        }
        if (!in_array($flag, array(1, 2))) {
            return 100000001;
        }

        $check = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->checkFavorite($uid,$mpid);
        // 收藏 & 有数据
        if ($flag === 1 && $check > 0) {
            return 100000003;
        }

        // 取消 & 没数据
        if ($flag === 2 && $check < 1) {
            return 100000003;
        }
        unset($uList, $rList);
        $favorite = 0;
        switch ($flag) {
            case 1:
                $favorite = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addFavorite($uid,$mpid);
                break;
            case 2:
                $favorite = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addDisFavorite($uid,$mpid);
                break;
        }
        return $favorite > 0 ? 1 : 0;
    }

    /**
     * [getUListByUids description]
     * @param  array  $uids [description]
     * @return [type]       [description]
     */
    public function getUListByUids(array $uids)
    {
        $result = array();
        if (!$uids) {
            return $result;
        }
        $uList = \YClient\Text::inst("ZeroMpai")->setClass("User")->getUListByUids($uids);
        if (!$uList) {
            return $result;
        }
        foreach ($uList as $u) {
            $result[$u['id']] = array(
                "rname" => $u['nickname'] ? dataEscape($u['nickname']) : UserLogic::instance()->getNickName(),
                "avatar" => $u['photopath'] ? $this->getImgUrl($u['id'], 1, 1) : "",
            );
        }
        unset($uList);
        return $result;
    }

    /**
     * addLike.
     *
     * @param [type] $uid  [description]
     * @param [type] $mpid [description]
     * @param [type] $flag [description]
     */
    public function addLike($uid, $mpid, $flag)
    {
        if (!is_numeric($uid) || !is_numeric($mpid)) {
            return 100000001;
        }
        if (!in_array($flag, array(1, 2))) {
            return 100000001;
        }
        $check = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->checkLike(
            $uid, \Common\Tool::OBJECT_TYPE_MPAI, $mpid
        );

        // 点赞 & 有数据
        if ($flag === 1 && $check > 0) {
            return 100000003;
        }
        // 取消 & 没数据
        if ($flag === 2 && $check < 1) {
            return 100000003;
        }

        $like = 0;
        switch ($flag) {
            case 1:
                $like = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addLike(
                    $uid, \Common\Tool::OBJECT_TYPE_MPAI, $mpid
                );
                break;
            case 2:
                $like = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addDislike(
                    $uid, \Common\Tool::OBJECT_TYPE_MPAI, $mpid
                );
                break;
        }
        // like
        if ($like) {
            $count = $flag ? "+1" : "-1";
            \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->updateMpaiLikeCount($mpid, $count);
        }
        return $like;
    }

    /**
     * addReport
     * @param [type] $uid  [description]
     * @param [type] $mpid [description]
     */
    public function addReport($uid, $mpid)
    {
        if (!is_numeric($uid) || !is_numeric($mpid)) {
            return 100000001;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addReport($uid, $mpid);
    }

    /**
     * checkFile
     * @param  [type] $fname [description]
     * @return [type]        [description]
     */
    public function checkFile($fname)
    {
        if (!$fname) {
            return 0;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->checkFile($fname);
    }

    /**
     * [getFileInfo description]
     * @param  [type] $fid [description]
     * @return [type]      [description]
     */
    public function getFileInfoByFname($fileName)
    {
        if (!$fileName) {
            return array();
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getFileInfoByFname($fileName);
    }

    /**
     * [addFabu description]
     * @param [type] $uid    [description]
     * @param [type] $params [description]
     */
    public function addFabu($uid, $params, $tgIds, $fparams)
    {
        if (!$uid || !$params || !$fparams) {
            return 100000001;
        }

        $tgIds = trim($tgIds, ",");
        $tgIds = $tgIds ? explode(",", $tgIds) : array();

        if (count($tgIds) > C("FABU_TAG_LIMIT")) {
            return 100110004;
        }

        $fabu = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addFabu($uid, $params);
        if ($fabu) {
            // add tag.
            if ($tgIds) \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addFabuTag($fabu, $tgIds);
            // add file.
            if (!\YClient\Text::inst("ZeroMpai")->setClass("Mpai")->checkFile($fparams['fname'])) {
                $fid = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addFile($fparams);
            }
        }
        return $fabu ? 1 : 0;
    }

    /**
     * [getTypeNotList description]
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function getTypeNotList($uid, $maxId, $limit)
    {
        if (!is_numeric($uid)) {
            return 100000001;
        }
        $data = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getTypeNotList($uid, $maxId, $limit);
        $result = array();
        $returnMax = 0;
        if (!$data) {
            return array(
                "list" => $result,
                "max_id" => $returnMax,
                "limit" => $limit,
            );
        }
        foreach ($data as $p) {
            $result[] = array(
                "tnid" => $p['tnid'],
                "type" => $p['types'],
                "type_name" => $this->getNotTypeName($p['types']),
                "nid" => $p['nid'],
                "content" => dataEscape($p['content']),
                "created" => $p['created'],
                "created_text" => timeFormat($p['created']),
                "unread" => \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getNoticeUnread($p['types'], $uid),
            );
            $returnMax = $p['tnid'];
        }
        unset($data);
        return array(
            "list" => $result,
            "max_id" => $returnMax,
            "limit" => $limit,
        );
    }

    /**
     * [getNotTypeName description]
     * @param  [type] $tnid [description]
     * @return [type]       [description]
     */
    private function getNotTypeName($tnid)
    {
        $result = "";
        switch ($tnid) {
            case 1:
                $result = C("LANG.100000013");
                break;
            case 2:
                $result = C("LANG.100110010");
                break;
            default:
                $result = "";
                break;
        }
        return $result;
    }

    /**
     * [checkNoticeUser description]
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function checkNoticeUser($uid, $nid)
    {
        if (!is_numeric($uid) || !is_numeric($nid)) {
            return 100000001;
        }
        return \YClient\Text::inst("ZeroNotice")->setClass("Notice")->checkNoticeUser($uid, $nid);
    }

    /**
     * [delNotice description]
     * @param  [type] $uid [description]
     * @param  [type] $nid [description]
     * @return [type]      [description]
     */
    public function delNotice($uid, $nid)
    {
        if (!is_numeric($uid) || !is_numeric($nid)) {
            return 100000001;
        }
        //TABLE_NEWSINFO   查询消息信息
        if (! $noticeInfo = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getNoticeInfo($nid)) {
            return 100000001;
        }
        //TABLE_NEW_OPERA  状态改成0
        $del = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->delNotice($uid, $nid);
        if ($del) {
            // 判断通知分类有没有这条数据
            if (\YClient\Text::inst("ZeroNotice")->setClass("Notice")->checkTypeNotice($uid, $noticeInfo['types']) < 1) {
                return $del;
            }
            // 判断是否是最后一条数据
            $typeNotceIds = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getNoticeIdList($noticeInfo['types']);
            if (!$typeNotceIds) {
                \YClient\Text::inst("ZeroNotice")->setClass("Notice")->delTypeNotice($uid, $noticeInfo['types']);
                return $del;
            }
            if (count($typeNotceIds) == 1 && $typeNotceIds[$nid]) {
                \YClient\Text::inst("ZeroNotice")->setClass("Notice")->delTypeNotice($uid, $noticeInfo['types']);
                return $del;
            }
            // 取最近一条数据
            $lastNoticeInfo = \YClient\Text::inst("ZeroNotice")->setClass("Notice")->getTypeLastNotice($uid, $noticeInfo['types'], $typeNotceIds);
            if (!$lastNoticeInfo) {
                \YClient\Text::inst("ZeroNotice")->setClass("Notice")->delTypeNotice($uid, $noticeInfo['types']);
                return $del;
            }
            $params = array(
                "nid" => $lastNoticeInfo['id'],
                "content" => $lastNoticeInfo['content'],
                "created" => $lastNoticeInfo['creattime'],
            );
            \YClient\Text::inst("ZeroNotice")->setClass("Notice")->updateTypeNotice($uid, $noticeInfo['types'], $params);
            unset($params, $noticeInfo, $lastNoticeInfo);
        }
        return $del;
    }

    /**
     * [getReplyList description]
     * @param  [type]  $mpid  [description]
     * @param  integer $maxId [description]
     * @param  integer $limit [description]
     * @return [type]         [description]
     */
    public function getReplyList($mpid, $maxId = 0, $limit = 20)
    {
        if (!$mpid) {
            return 100000001;
        }

        $rList = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getReplyList($mpid, $maxId, $limit);
        $result = array();

        if (!$rList) {
            return array("list" => $result, "max_id" => 0, "limit" => $limit);
        }

        $maxReturn = 0;
        $uids = array();
        foreach ($rList as $r) {
            $uids[$r['uid']] = $r['uid'];
            $uids[$r['ruid']] = $r['ruid'];
            $maxReturn = $r['rid'];
        }

        $uList = $this->getUListByUids($uids);
        unset($uids);

        $result = $this->dealReplyList($rList, $uList);
        unset($uList, $rList);

        return array("list" => $result, "max_id" => $maxReturn, "limit" => $limit);
    }

    /**
     * 处理评论数据.
     * 
     * @param  [type] $rList [description]
     * @param  [type] $uList [description]
     * @return [type]        [description]
     */
    private function dealReplyList($rList, $uList)
    {
        $result = array();
        if (!$rList) {
            return $result;
        }

        foreach ($rList as $rl) {
            $urArr = $uArr = (object) array();
            if ($rl['ruid'] > 0) {
                $urArr = array(
                    "uid" => $rl['ruid'],
                    "rname" => isset($uList[$rl['ruid']]['rname']) ? $uList[$rl['ruid']]['rname'] : "",
                    "avatar" => isset($uList[$rl['ruid']]['avatar']) ? $uList[$rl['ruid']]['avatar'] : "",
                );
            }
            if ($rl['uid'] > 0) {
                $uArr = array(
                    "uid" => $rl['uid'],
                    "rname" => isset($uList[$rl['uid']]['rname']) ? $uList[$rl['uid']]['rname'] : "",
                    "avatar" => isset($uList[$rl['uid']]['avatar']) ? $uList[$rl['uid']]['avatar'] : "",
                );
            }
            $result[] = array(
                "rid" => $rl['rid'],
                "user" => $uArr,
                "ruser" => $urArr,
                "created" => $rl['add_time_int'],
                "created_text" => timeFormat($rl['add_time_int']),
                "content" => dataEscape($rl['content']),
            );
        }
        return $result;
    }

    /**
     * 获取发现条件.
     * 
     * @param  [type] $type  [description]
     * @param  [type] $tagId [description]
     * @param  [type] $uid   [description]
     * @param  [type] $maxId [description]
     * @param  [type] $num   [description]
     * @return [type]        [description]
     */
    private function getFoundWhere($type, $tagId, $uid, $maxId = 0)
    {
        $errorCode = true;
        $fwhere = $type == 3 ? $this->dealFoundByFollow($uid, $errorCode) : "";
        if (!$errorCode) {
            return false;
        }
        $where = " where status=1 ";
        // 处理标签对应的作品IDS。
        $where .= $tagId > 0 ? $this->dealFoundByTag($tagId) : "";
        // 处理推荐和排序
        $where .= $type == 1 ? " and isrecommend=1 " : "";
        // 处理关注
        $where .= $fwhere;
        $where .= $maxId > 0 ? " and id<{$maxId} " : "";
        $where .= " and isview=1 ";
        return $where;
    }

    /**
     * 获取发现总数.
     * 
     * @param  [type] $type  [description]
     * @param  [type] $tagId [description]
     * @param  [type] $uid   [description]
     * @param  [type] $maxId [description]
     * @param  [type] $num   [description]
     * @return [type]        [description]
     */
    public function getFoundCount($type, $tagId, $uid)
    {
        $where = $this->getFoundWhere($type, $tagId, $uid, 0);
        if ($where === false) {
            return false;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getFoundCount($where);
    }

    /**
     * 获取发现的数据.
     * 
     * @param  [type] $type  [description]
     * @param  [type] $tagId [description]
     * @param  [type] $maxId [description]
     * @param  [type] $num   [description]
     * @return [type]        [description]
     */
    public function getFoundData($type, $tagId, $uid, $maxId, $num)
    {
        // 判断是否有关注.
        $where = $this->getFoundWhere($type, $tagId, $uid, $maxId);
        $order = $type == 1 ? " order by istop desc, id desc " : " order by id desc ";
        $limit = $num > 0 ? " limit {$num} " : "";
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getFindData($where, $order, $limit);
    }

    /**
     * 通过标签获取所有作品IDS组成的SQL
     * 
     * @param  [type] $tagId [description]
     * @return [type]        [description]
     */
    private function dealFoundByTag($tagId)
    {
        $fids = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getFindByTag($tagId);
        $fids = is_array($fids) ? implode(",", array_map('intval', $fids)) : $fids;
        return $fids ? " and id in ({$fids}) " : "";
    }

    /**
     * 通过用户ID获取所有关注的人的IDS组成的SQL
     * 
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    private function dealFoundByFollow($uid, &$errorCode)
    {
        $fuids = \YClient\Text::inst("ZeroMpai")->setClass("User")->getFollowUids($uid);
        $fuids = is_array($fuids) ? implode(",", array_map('intval', $fuids)) : $fuids;
        $errorCode = $fuids ? true : false;
        return $fuids ? " and uid in ({$fuids}) " : " and uid<1 ";
    }

    /**
     * 处理发现数据.
     * 
     * @param  [type] $arr     [description]
     * @param  [type] &$result [description]
     * @return [type]          [description]
     */
    public function dealFoundData($arr, &$result, &$returnMax)
    {
        if (!$arr) {
            $result = array();
        }
        $uids = array();
        foreach ($arr as $r) {
            $uids[$r['uid']] = $r['uid'];
        }

        $uList = $this->getUListByUids($uids);
        unset($uids);
        $mid = (int) session("uid");
        foreach ($arr as $p) {
            $list = array(
                "id" => $p['id'],
                "mpid" => $p['id'],
                "user" => array(
                    "uid" => $p['uid'],
                    "rname" => isset($uList[$p['uid']]['rname']) ? $uList[$p['uid']]['rname'] : "",
                    "avatar" => isset($uList[$p['uid']]['avatar']) ? $uList[$p['uid']]['avatar'] : "",
                ),
                "created" => $p['creattime'],
                "created_text" => timeFormat($p['creattime']),
                "content" => dataEscape($p['content']),
                "img_url" => $p['types'] == 1 ? $this->getImgUrl($p['id'], 4, 4) : "",
                "img_origin_url" => $p['types'] == 1 ? $this->getImgUrl($p['id'], 4, 6) : "",
                "video_url" => $p['types'] == 2 ? $this->getImgUrl($p['id'], 3, 4) : "",
                "img_urls" => $p['types'] == 1 ? array(array("surl" => $this->getImgUrl($p['id'], 4, 4), "burl" => $this->getImgUrl($p['id'], 4, 5), "lurl" => $this->getImgUrl($p['id'], 4, 6))) : array(),
                "follow_state" => $mid ? \YClient\Text::inst("ZeroMpai")->setClass("User")->checkFollowed($mid, \Common\Tool::OBJECT_TYPE_USER, $p['uid']) : 0,
                "like_state" => $mid ? \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->checkMpaiLike($mid, $p['id']) : 0,
                "favorite_state" => $mid ? \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->checkFavorite($mid, $p['id']) : 0,
                "like_count" => \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getMpaiLikeCount($p['id']),
                "comment_count" => \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getMpaiCommentCount($p['id']),
                "tag_list" => \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getMpaiTagList($p['id']),
                "locat" => $p['address'],
                "types" => $p['types'],
                "url_width" => $p['url_width'],
                "url_height" => $p['url_height'],
                "flow_url"=> "",
                "looknum" => $p['looknum'],
            );

            if ($p['from_step'] == 2 || $p['from_step'] == 3) {
                $filetype = substr($p['url'], 0, 2);
                $st = $filetype=="AB" || $filetype=="IB";
                // 此处并没有考虑缩略图的情况。
                if ($st) {
                    $list['img_url'] = $this->getImgUrl($p['id'], 6, 4);
                    $list['img_origin_url'] = $list['img_url'];
                    $list['flow_url'] = C("FLOW_URL")."?uid=".$p['uid']."&vname=".$p['url']."&lan=".C("LAN");
                    $list['video_url'] = '';
                } else {
                    $list['img_url'] = "";
                    $list['img_origin_url'] = "";
                    $list['video_url'] = $this->getImgUrl($p['id'], 3, 4);
                }
            }

            $returnMax = $p['id'];
            $result[] = $list;
        }
    }

    /**
     * getFavList
     * @param  [type] $uid   [description]
     * @param  [type] $maxId [description]
     * @param  [type] $limit [description]
     * @return [type]        [description]
     */
    public function getFavList($uid, $maxId, $limit)
    {
        $data = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getFavList($uid, $maxId, $limit);
        if (!$data || !$data['list']) {
            return array();
        }

        $result = array();
        $this->dealFoundData($data['list'], $result);
        
        $resultNew = array(
            "list" => $result,
            "max_id" => (int) $data['return_max'],
            "limit" => $limit,
        );
        unset($data);
        return $resultNew;
    }

    /**
     * [dealMpIds description]
     * @param  [type] $result [description]
     * @return [type]         [description]
     */
    private function dealMpIds($result)
    {
        $returnMax = 0;
        $mpids = array();
        foreach ($result as $r) {
            $mpids[$r['mpid']] = $r['mpid'];
            $returnMax = $r['fid'];
        }

        return array(
            "return_max" => $returnMax,
            "mpids" => $mpids,
        );
    }

    /**
     * [addMpailCount description]
     * @param [type]  $uid    [description]
     * @param [type]  $mpid   [description]
     * @param integer $number [description]
     */
    public function addMpailCount($uid, $mpid, $number = 1)
    {
        if (!$mpid) {
            return 100000001;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addMpailCount($uid, $mpid, $number);
    }

    /**
     * [updateVideo description]
     * @param  [type] $message [description]
     * @return [type]          [description]
     */
    public function updateVideo($message)
    {
        $mediadata=$message['MediaWorkflowExecution'];
        $fname=$this->_getVideoData($mediadata['Input']);
        $transcodedata=$this->_getTranscodeData($mediadata['ActivityList']);

        // 判断这个文件是否存在
        if(!$fid = \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->checkFile($fname)) {
            return false;
        }

        $trancode = 1;
        if ($transcodedata['transcode']=='Success') {
            $trancode = 2;
        } else if($transcodedata['transcode']=='Fail') {
            $trancode = 10;
        }

        \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->updateFile($fid, $fname, 1, $trancode);
        return true;
    }

    private function _getVideoData($videoarr)
    {
        if(empty($videoarr)){
            return false;
        }
        $objectData = isset($videoarr['InputFile']['Object']) ? $videoarr['InputFile']['Object'] : "";
        return $objectData ? str_replace(array(".mp4", "/"), "", strstr($objectData, "/")) : "";
    }

    private function _getTranscodeData($transcodearr){
        if(empty($transcodearr)){
            return false;
        }

        $transcode='';
        $transcodename='';
        // $snapshot='';
        foreach ($transcodearr as $key=>$val){
            if($val['Type']=='Transcode'){
                $transcode=$val['State'] ? 'Success':'Fail';
                $transcodename=$val['Name'];
            }
        }

        $returnarr=array(
            'transcode'=>$transcode,
            'transcodename'=>$transcodename,
            // 'snapshot'=>$snapshot,
        );
        return $returnarr;
    }

    /**
     * 一代设备分享.
     * 
     * @return [type] [description]
     */
    public function getMpShareByOne($fid)
    {
        $id = $fid; //fid 文件名称

        getLang($lan);
        $lang = C('LANG');

        if(!$id){
            return 'share_notfound';
        }
        $Model = M('Db'); // 实例化一个model对象 没有对应任何数据表
        $v = $Model->querySql("select * from mpai_video where filenames='".$id."'");
        if(!$v[0]['filenames']){
            return 'share_notfound';
        }
        $v = $v[0];
        $filename = $id;
        $arru['id'] = $v['uid'];
        $user = M('User');
        $field = array(
            'thumb_path',
            'photopath'
        );
        $data = $user->find($arru,$field);
        $videourl = get_file_url2(C('VIDEO_OUT_BUCKET'),'out480/'.$filename.'.mp4',600); //有get_file_url 改为get_file_url2
        $videourl = str_replace("zeroout.oss-cn-hangzhou.aliyuncs.com","dobbydown.zerotech.com",$videourl);
        $datau['video_url'] = $videourl;
        $datau['avatar'] = $this->getPhoto($data['photopath']);

        $json['user'] = $datau;
        $json['lang'] = array();
        $json['list'] = array();

        return $json;
    }

    /**
     * 二代设备分享.
     * 
     * @return [type] [description]
     */
    public function getMpShareByTwo($fid,$uid,$lan)
    {
        $id = $fid;
        $uid = $uid;
        $lan = $lan;
        
        getLang($lan);
        $lang = C('LANG');

        if(!$id){
            return 'share_notfound';
        }
        $Model = M('Db'); // 实例化一个model对象 没有对应任何数据表
        if($uid){
            $v = $Model->querySql("select * from mpai_video where uid=".$uid." and filenames='".$id."'");
        }else{
            $v = $Model->querySql("select * from mpai_video where filenames='".$id."'");
        }
        if(!$v[0]['filenames']){
            return 'share_notfound';
        }
        $v = $v[0];
        $filename = $id;
        $videoId = $v['id'];
        $arru['id'] = $v['uid'];
        $user = M('User');
        $field = array(
            'photopath',
            'nickname',
            'id'
        );
        $data = $user->findFieldOrder($arru,$field,null);
        
        $datau['avatar'] = $this->getPhoto($data['photopath']);
        $datau['nickname'] = $data['nickname']?$data['nickname']:"DOBBYuser";
        $datau['create_time'] = date('Y.m.d',$v['create_time']);
        $datau['looknum'] = $v['looknum'];

        if ($v['types'] == 0) {
            $videourl = get_file_url2(C('VIDEO_OUT_BUCKET'),'out1080/'.$filename.'.mp4',600);
            $videourl = str_replace("stszeroout.oss-cn-hangzhou.aliyuncs.com","videodown.zerotech.com",$videourl);
            $datau['video_url'] = $videourl;
            $datau['img_url'] = get_file_url2(C('VIDEO_OUT_BUCKET'),'outimg/'.$filename.'.jpg',600);
            $datau['img_url'] = str_replace("stszeroout.oss-cn-hangzhou.aliyuncs.com","videodown.zerotech.com",$datau['img_url']);
        } else {
            $datau['video_url'] = "";
            $datau['img_url'] = get_file_url2('user2upload', 'UploadsTest/'.$filename.'.jpg', 600);
            $datau['img_url'] = str_replace("user2upload.oss-cn-hangzhou.aliyuncs.com","udata.zerotech.com",$datau['img_url']);
        }
        
        $datau['types'] = $v['types']==1?1:($v['types']==0?2:3);
        
        $json['user'] = $datau;
        $json['lang'] = array(
            "share_title" => $lang["share_title"],
            "share_name" => $lang["share_name"],
            "share_down" => $lang["share_down"],
            "share_more" => $lang["share_more"],
            "share_open" => $lang["share_open"],
            "share_notfound" => $lang["share_notfound"],
            "share_buy_text" => $lang["share_buy_text"]?$lang["share_buy_text"]:"",
            "share_buy_url" => $lang["share_buy_url"]?$lang["share_buy_url"]:""
        );

        $datamore = $this->getMore($data['id']);
        $list = array();
        if($datamore){
            $i = 0;
            foreach ($datamore as $k => $v) {
                if (!$v['filenames']) continue;
                $list[$i]['u'] = $v['uid'];
                $list[$i]['fid'] = $v['filenames'];
                $list[$i]['looknum'] = $v['looknum'];
                $list[$i]['create_time'] = $v['create_time'];
                $list[$i]['img_url'] = $v['surl'];
                $list[$i]['lan'] = $lan?$lan:1;
                $list[$i]['nickname'] = $datau['nickname'];
                $list[$i]['types'] = $v['types']==1?1:($v['types']==0?2:3);
                $i++;
            }
        }
        $json['list'] = $list;
        $mapv['status'] = 1;
        $mapv['id'] = $videoId;
        $datav['looknum'] = (int)$v['looknum']+1;
        $video = M('Video');
        $video->save($datav,$mapv);

        return $json;
    }

    public function deleteRedis($flag, $id)
    {
        $key = "ZERO_MPAI_URL:{$id}";
        $redis = \Common\Redis\CRedis::cache('default');
        switch ($flag) {
            case 1:#用户图片
                $redis->del($key . "_1_1");
                $redis->del($key . "_1_1_1");
                $redis->del($key . "_1_2");
                $redis->del($key . "_1_2_1");
                break;
            case 5:#背景图
                $redis->del($key . "_5_3");
                $redis->del($key . "_5_3_1");
                $redis->del($key . "_5_4");
                $redis->del($key . "_5_4_1");
                break;
            case 3:#首诊图
                $redis->del($key . "_3_3");
                $redis->del($key . "_3_3_1");
                $redis->del($key . "_3_4");
                $redis->del($key . "_3_4_1");
                break;
            case 2:#视频
                $redis->del($key . "_2_0");
                $redis->del($key . "_2_0_1");
                $redis->del($key . "_2_1");
                $redis->del($key . "_2_1_1");
                $redis->del($key . "_2_2");
                $redis->del($key . "_2_2_1");
                $redis->del($key . "_2_3");
                $redis->del($key . "_2_3_1");
                break;
            case 4:#发布图片
                $redis->del($key . "_4_3");
                $redis->del($key . "_4_3_1");
                $redis->del($key . "_4_4");
                $redis->del($key . "_4_4_1");
                $redis->del($key . "_4_5");
                $redis->del($key . "_4_5_1");
                $redis->del($key . "_4_6");
                $redis->del($key . "_4_6_1");
                break;
            case 6:#流图
                $redis->del($key . "_6_3");
                $redis->del($key . "_6_3_1");
                $redis->del($key . "_6_4");
                $redis->del($key . "_6_4_1");
                break;
        }
        return true;
    }

    /**
     * [getUnreadNewCount description]
     * @param  [type] $maxId [description]
     * @param  [type] $uid   [description]
     * @return [type]        [description]
     */
    public function getUnreadNewCount($maxId, $uid)
    {
        if ($maxId < 1) {
            return 0;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->getUnreadNewCount($maxId, $uid);
    }

    /**
     * 二代发现分享.
     * 
     * @return [type] [description]
     */
    public function getMpShareNew($mpid)
    {
        if (!is_numeric($mpid)) {
            return 100000001;
        }
        $mpInfo = $this->getMpaiInfo($mpid);
        if (!$mpInfo) {
            return 100000027;
        }
        $mNew = $mpInfo;
        $fileInfo = $this->getFileInfoByFname($mpInfo['url']);
        if (!$fileInfo) {
            return 100000027;
        }
        if ($fileInfo['trans_status'] == 10) {
            return 100000026;
        }
        unset($fileInfo);
        if ($mpInfo['types'] == 2) {
            $mpInfo['url'] = $this->getVideoUrl($mpid, 2);
        } else {
            // 不是视频的话全部暂时当做图片来处理.
            $mpInfo['url'] = $this->getImgUrl($mpid, 4, 4);
        }
        $mpInfo['img_url'] = $mpInfo['types'] != 2 ? $mpInfo['url'] : $this->getImgUrl($mpid, 3, 4);

        // 分享视频，此处走的是mpai_video表数据，此处修改by shaobo
        if ($mNew['from_step'] == 2) {
            $mpInfo['img_url'] = get_file_url2(C("VIDEO_OUT_BUCKET"), "outimg"."/".$mNew['url'].".jpg".getImgStyle(3), C("urlExpire"));
            $mpInfo['url'] = get_file_url2(C("VIDEO_OUT_BUCKET"), "out".getVideoStyle(0)."/".$mNew['url'].".mp4", C("urlExpire"));
        }
        unset($mNew);

        $uInfo = \MpaiApi\Logic\UserLogic::instance()->getUInfo($mpInfo['uid']);
        $result['user'] = array(
            "uid" => $mpInfo['uid'],
            "nickname" => $uInfo['nname'],
            "avatar" => $uInfo['avatar'],
            "create_time" => date("Y.m.d", $mpInfo['creattime']),
            "looknum" => $mpInfo['looknum'],
            "img_url" => $mpInfo['types'] != 2 ? $mpInfo['url'] : $mpInfo['img_url'], #图片或首诊图
            "video_url" => $mpInfo['types'] == 2 ? $mpInfo['url'] : "", #视频地址
            "locate" => $mpInfo['address'], # 地址
            "types" => $mpInfo['types'], #1-图片 2-视频 3-流图
        );
        unset($uInfo);

        // 获取我的作品列表 6
        $result['list'] = \MpaiApi\Logic\UserLogic::instance()->getMyMpList($mpInfo['uid']);
        $result['list'] = $this->dealMyMpList($result['list']);

        // 获取语言
        $result['lang'] = array(
            "share_title" => C("LANG.share_title"),
            "share_name" => C("LANG.share_name"),
            "share_buy_url" => C("LANG.share_buy_url"),
            "share_buy_text" => C("LANG.share_buy_text"),
            "share_down" => C("LANG.share_down"),
            "share_more" => C("LANG.share_more"),
            "share_open" => C("LANG.share_open"),
            "share_notfound" => C("LANG.share_notfound"),
        );

        // 记录数
        \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->addMpailCount($mpInfo['uid'], $mpid);
        unset($mpInfo);
        return $result;
    }

    /**
     * [dealMyMpList description]
     * @param  [type] $result [description]
     * @return [type]         [description]
     */
    private function dealMyMpList($data)
    {
        $result = array();
        if (!$data) {
            return $result;
        }
        foreach ($data as $p) {
            $result[] = array(
                "mpid" => $p['mpid'], #美拍ID
                "u" => $p['user']['uid'],
                "nickname" => $p['user']['rname'],
                "fid" => "",
                "create_time" => date("Y.m.d", $p['created']),
                "looknum" => $p['looknum'],
                "img_url" => $p['img_url'],
                "video_url" => $p['video_url'],
                "types" => $p['types'], # 1-图片 2-视频 3-流图
                "flow_url" => $p['flow_url'] # 流图地址
            );
        }
        unset($data);

        return $result;
    }
    public function getMore($uid){
        $id = I('get.fid');
        if(!$id){
            return null;
        }
        $map['uid'] = $uid;
        $map['status'] = 1;
        $map['filenames'] = array('neq',$id);

        $video = M('Video');
        $data = $video->select($map,'id desc',6);
        $aliConfs = C("ALIOSS_CONFIG2");
        foreach ($data as $k => &$v) {
            if ($v['types'] == 0) {
                $v['surl'] = get_file_url2(C('VIDEO_OUT_BUCKET'), 'outimg/'.$v['filenames'].'.jpg!Avatar_600', 600);
                $v['surl'] = str_replace("stszeroout.oss-cn-hangzhou.aliyuncs.com", "videodown.zerotech.com", $v['surl']);
            } else {
                $v['surl'] = get_file_url2('user2upload', 'UploadsTest/'.$v['filenames'].'.jpg!Avatar_600', 600);
                $v['surl'] = str_replace("user2upload.oss-cn-hangzhou.aliyuncs.com", "udata.zerotech.com", $v['surl']);
            }
            $v['create_time'] = date('Y.m.d',$v['create_time']);
        }
        return $data;
    }

    protected function getPhoto($path){
        if(!trim($path)){
            $userpath = get_file_url2(C('TX_BUCKET'),'user_head.png',500);         
            $userpath = str_replace("user2upload.oss-cn-hangzhou.aliyuncs.com","udata.zerotech.com",$userpath);
            return $userpath;
        }
        $str = $path;
        $arrdata = explode('@', $str);
        if(count($arrdata)==6){
            return $this->getImg($arrdata);
        }
        $arr = explode('/', $str);
        $anum = count($arr);
        if($anum==1){
            return get_file_url2(C('TX_BUCKET'),C("TX_OBJECT2")."/".$path.".jpg!Avatar_200",500);
        }
        if(count($arr)<2){
            return $str;
        }
        $num = count($arr);
        $pic = $arr[$num-1];
        $dir = $arr[$num-2];
        $picurl = get_file_url2(C('TX_BUCKET'),C("TX_OBJECT")."/".$dir."/".$pic,500);
        $picurl = str_replace("user2upload.oss-cn-hangzhou.aliyuncs.com","udata.zerotech.com",$picurl);
        return $picurl;
    }

    protected function getImg($arr){
        $bucket = 'zeroimg';
        $object = 'yuntai'."/".$arr[2]."/".$arr[3]."/".$arr[4]."/".$arr[5];
        return get_file_url2($bucket,$object,500);
    }

    /**
     * 获取puststatus数据.
     * 
     * @return array.
     */
    public function getPushStat($pushDate)
    {
        if (!$pushDate) {
            return null;
        }
        $startTime = strtotime($pushDate . " 00:00:00");
        $endTime = strtotime($pushDate . " 23:59:59");
        $data = \YClient\Text::inst("ZeroMpai")->setClass("Pushstat")->getData($startTime, $endTime);
        return $data;
    }

    /**
     * 处理通知.
     * 
     * @param  [type] $uid  [description]
     * @param  [type] $ruid [description]
     * @param  [type] $mpid [description]
     * @return [type]       [description]
     */
    public function dealNotice($qzid, $uid, $ruid, $ouid, $mpid)
    {
        $rtype = !$ruid ? 1 : 2;
        $ruid = !$ruid ? $ouid : $ruid;
        if ($uid == $ruid) {
            $ruid = 0;
        }
        \MpaiApi\Logic\NoticeLogic::instance()->Data2Notice($qzid, $uid, $ruid, $mpid, $rtype);
        return true;
    }

    /**
     * 删除某个作品的对应的映射数据.
     * 
     * @param  [type] $mpid [description]
     * @return [type]       [description]
     */
    public function delMpaiFavorite($mpid)
    {
        if (!is_numeric($mpid)) {
            return false;
        }
        return \YClient\Text::inst("ZeroMpai")->setClass("Mpai")->delMpaiFavorite($mpid);
    }
}
