<?php

namespace MpaiApi\Logic;

use \Redis\RedisMultiCache;

/**
 * 处理逻辑模型
 */
class DragLogic
{
    public $redis;

    /**
     * 单例
     * @var type
     */
    private static $_singletonObject = null;

    private function __construct()
    {
        $this->redis = \Common\Redis\CRedis::cache('default');
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

    /**
     * 场景深显示页，统计访问次数
     * @param    int     $uid     用户UID
     * @param    string  $vname   视频唯一标识
     */
    public function setCounter($uid, $vname){
        \YClient\Text::inst("ZeroMpai")->setClass("Drag")->setCounter($uid, $vname);
    }

    /**
     * 场景深显示页，获取统计访问次数
     * @param    int     $uid     用户UID
     * @param    string  $vname   视频唯一标识
     * @return mixed
     */
    public function getCounter($uid, $vname){
        $count =  \YClient\Text::inst("ZeroMpai")->setClass("Drag")->getCounter($uid, $vname);
        return $count;
    }

    /**
     * 获取场景深数据缓存
     * @param    int    $uid    用户UID
     * @param    string  $vname   视频唯一标识
     * @return mixed
     */
    public function getDragListByRedis($uid, $vname){
        return $this->redis->GET("ZERO_DRAG_LIST:".$uid."_".$vname);
    }




    /**
     * 添加图片信息
     * @param  int    $uid   用户UID
     * @param  string $fname 图片文件名 以“，”号分隔
     * @param  string $fext  图片文件后缀
     * @param  string $vname 视频标识
     * @param  string $fsize 文件大小
     * @return int
     */
    public function addImage($uid, $fname, $fext = 'jpg', $vname){
        if(!$uid || !$fname || !$vname){
            return 100000001;
        }
        $imageInfo = array(
            'uid'         => $uid,
            'file_name'   => $fname,
            'file_extend' => $fext,
            'video_name'  => $vname,
            'create_time' => time(),
        );
        $data = \YClient\Text::inst("ZeroMpai")->setClass("Drag")->addImage($imageInfo);
        if($data){
            //处理第一张图片
            $frist_img = '';
            $imageArr = explode(',', $fname);
            if(isset($imageArr[0])){
                $extend = $fext ? $fext : 'jpg';
                $frist_img = get_file_url2(C("DRAG_OSS_BUCKET"), C("DRAG_OSS_OBJECT")."/".$imageArr[0].".".$extend, 3600);
            }
            $lang = C('LANG');
            return array(
                'drag_url' => C("DRAG_SHARE_URL")."/drag/index?uid=".$uid."&vname=".$vname,
                'frist_img' => $frist_img,
                'drag_title' =>  $lang['100110006'],
            );
        }else{
            return 100000003;
        }

    }

    /**
     * 获取图片数据信息
     * @param   string    $vName   视频标识
     * @param   int       $uid     用户UID
     * @return  array     $data
     */

    public function getDragList($uid, $vName){
        if(!$vName && !$uid){
            return 100000001;
        }
        //判断是否有缓存
        $list = $this->redis->get("ZERO_DRAG_LIST:".$uid."_".$vName);
        if($list){
            $dragList = json_decode($list, true);
            $dragList['count'] = $this->getCounter($uid, $vName);
        }else{
            $data = \YClient\Text::inst("ZeroMpai")->setClass("Drag")->getDragInfo($uid, $vName);
            if(!is_array($data)){
                return 100000002;
            }
            $pathStr = isset($data['file_name']) && $data['file_name'] ? $data['file_name'] : '';
            $extend = isset($data['file_extend']) && $data['file_extend'] ? $data['file_extend'] : 'jpg';
            $pathArr = explode(',', $pathStr);
            $furlArr = array();
            $prefixArr = array();
            if(is_array($pathArr) && !empty($pathArr)){
               $burls = C('DRAG_OSS_BIND_URLS');
               $bq = 0;
               $bqm = 0;
               foreach($pathArr as $key => $path){
                   $url = get_file_url2(C("DRAG_OSS_BUCKET"), C("DRAG_OSS_OBJECT").'/'.$path.'.'.$extend, C("urlExpire"));
                   if ($bq >= C("DRAG_OSS_BIND_COUNT")) {
                        $bqm ++;
                        $bq = 0;
                   }
                   // 处理是否加速
                   if (C("DRAG_JIASU_OPEN")) {
                        $url = str_replace(C('DRAG_OSS_TEST_URL'), $burls[$bqm], $url);
                   }
                   $furlArr[$key] = $url;

                   // deal with prefix
                   $prefixs = explode(".jpg", $url);
                   $prefixStr1 = $prefixs[0];
                   $prefixStr2 = $prefixs[1];
                   $prefixArr[$key] = array(
                        "a_url" => substr($prefixStr1, 0, -3),
                        "b_url" => substr($prefixStr2, 1),
                   );

                   $bq ++;
               }
            }

            //获取redis中场景深页面访问次数。
            $count = $this->getCounter($uid, $vName);
            $dragList = array(
                'id' => isset($data['id']) ? $data['id'] : 0,
                'uid' => isset($data['uid']) ? $data['uid'] : 0,
                'file_url' => $furlArr,
                'url_frag' => $prefixArr,
                'count' => $count ? $count : $data['count'],
            );
            $this->redis->setex("ZERO_DRAG_LIST:".$uid."_".$vName, C("urlExpire"), json_encode($dragList));
        }

        return $dragList;
    }

    /**
     * 验证场景深图片是否上传过。
     *    上传过     返回分享链接， 首张图， 标题
     *    没上传过   返回oos上传配置
     * @param    int      $uid    用户UID
     * @param    string   $vname  切割前视频MD5
     * @return   array|int
     */
    public function checkDrag($uid, $vname){
        if(!$vname){
            return 100000001;
        }
        //查表中是否有上传的场景深
        $data = \YClient\Text::inst("ZeroMpai")->setClass("Drag")->getDragList($vname);
        if(isset($data[0]) && is_array($data[0])){
            //检查当前用户是否有此场景深图片，没有添加用户对应记录
            $firstDrag = $data[0];
            $dragInfo = \YClient\Text::inst("ZeroMpai")->setClass("Drag")->getDragInfo($uid, $vname);
            if(!$dragInfo){
                $imageInfo = array(
                    'uid'          => $uid,
                    'file_name'   => isset($firstDrag['file_name']) ? $firstDrag['file_name'] : '',
                    'file_extend' => isset($firstDrag['file_extend']) ? $firstDrag['file_extend'] : 'jpg',
                    'video_name'  => $vname,
                    'create_time' => time(),
                );
                \YClient\Text::inst("ZeroMpai")->setClass("Drag")->addImage($imageInfo);
            }
            //处理第一张图片
            $frist_img = '';
            if(isset($firstDrag['file_name']) && isset($firstDrag['file_extend'])){
                $imageArr = explode(',', $firstDrag['file_name']);
                if(isset($imageArr[0])){
                    $extend = $firstDrag['file_extend'] ? $firstDrag['file_extend'] : 'jpg';
                    $frist_img = get_file_url2(C("DRAG_OSS_BUCKET"), C("DRAG_OSS_OBJECT")."/".$imageArr[0].".".$extend, 3600);
                }
            }
            $lang = C('LANG');
            $result = array(
                'status'         => 1,
                'drag_url'       => C("DRAG_SHARE_URL")."/drag/index?uid=".$uid."&vname=".$vname,
                'frist_img'      => $frist_img,
                'drag_title'     =>  $lang['100110006'],
            );

        }else{
            //没上传过返回OSS上传临时配置
            $str = newDragSts();
            $strarr = json_decode($str,true);
            $result = array(
                'status' => 0,
                'bucketname' => C("DRAG_OSS_BUCKET"),
                'objectname' => C("DRAG_OSS_OBJECT"),
                'endpoint'    => C("DRAG_OSS_ENDPOINT"),
                'keyid'       =>  isset($strarr['AccessKeyId']) ? $strarr['AccessKeyId'] :'',
                'keysecret'   => isset($strarr['AccessKeySecret']) ? $strarr['AccessKeySecret'] : '',
                'keytoken'    => isset($strarr['SecurityToken']) ? $strarr['SecurityToken'] : '',
            );
        }
        return $result;
    }

}
