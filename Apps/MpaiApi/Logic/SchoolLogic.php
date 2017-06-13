<?php

namespace MpaiApi\Logic;

/**
 * 处理逻辑模型
 */
class SchoolLogic
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

    public function getFileList($type, $maxId = 0, $limit = 5)
    {
        if (!is_numeric($type)) {
            return 100000001;
        }

        $data = \YClient\Text::inst("ZeroMpai")->setClass("School")->getFileList($type, $maxId, $limit);
        if (!$data) {
            return 100000002;
        }
        $returnMax = 0;
        foreach($data as $key => $value){
            //逻辑处理
            //$value['path'] = 'A177cba06986fe87ff4f15a110584c2db';

            if(isset($value['type']) && $value['type'] == 1){
                $extend =  'mp4';
                $bucket = 'zeroout';
                $object_file = 'out1080/';
                $object_icon = 'outimg/';
            }else{
                $extend =  'pdf';
                $bucket = 'user2test';
                $object_file = 'school-pdf/';
                $object_icon = 'school-pdf/';
            }
            $fileUrl = get_file_url2($bucket, $object_file.$value['path'].'.'.$extend, 600);
            //$fileUrl = str_replace("stszeroout.oss-cn-hangzhou.aliyuncs.com","videodown.zerotech.com",$fileUrl);
            $data[$key]['file_url'] = $fileUrl;
            $iconUrl = get_file_url2($bucket, $object_icon.$value['icon'].'.jpg', 600);
            $data[$key]['icon_url'] = $iconUrl;
            $data[$key]['create_time'] = time();
            $data[$key]['timeout']  = 600;
            unset($data[$key]['path']);
            unset($data[$key]['icon']);
            $returnMax = $value['id'];
        }
        return array(
            "list" => $data,
            "max_id" => $returnMax,
            "limit" => $limit,
        );

    }

    public function getVideoUrl($id, $token){
        if (!is_numeric($id)) {
            return 100000001;
        }
        //根据id查表获取地址
        $data = \YClient\Text::inst("ZeroMpai")->setClass("School")->getVideoUrl($id);
        if (!$data) {
            return 100000002;
        }
        $path = isset($data['path']) ? $data['path'] : '';
        $fileMd5 = isset($data['file_md5']) ? $data['file_md5'] : '';
        //验证token
        $checkToken = $this->checkToken($token, $fileMd5);
        if(!$checkToken){
            return 100000001;
        }
        $fileUrl = get_file_url2('zeroout','out1080/'.$path.'.mp4', 600);

        //$fileUrl = str_replace("stszeroout.oss-cn-hangzhou.aliyuncs.com","videodown.zerotech.com", $fileUrl);

        return array(
            'video_url' => $fileUrl,
            'timeout' => 600,
        );
    }

    public function getDocUrl($id, $token)
    {
        if (!is_numeric($id) || !$token) {
            return 100000001;
        }
        //根据id查表获取地址
        $data = \YClient\Text::inst("ZeroMpai")->setClass("School")->getDocById($id);
        if (!$data) {
            return 100000002;
        }

        $path = isset($data['path']) ? $data['path'] : '';
	$fileMd5 = isset($data['file_md5']) ? $data['file_md5'] : '';
        //验证token
        $checkToken = $this->checkToken($token, $fileMd5);
        if(!$checkToken){
            return 100000001;
        }
        //$path = 'A177cba06986fe87ff4f15a110584c2db';
        //取阿里云链接（加配置）
        $fileUrl = get_file_url2('user2test','school-pdf/'.$path.'.pdf', 600);

        //$fileUrl = str_replace("stszeroout.oss-cn-hangzhou.aliyuncs.com","videodown.zerotech.com", $fileUrl);

        return array(
            'doc_url' => $fileUrl,
            'timeout' => 600,
        );

    }

    public function checkToken($token, $fileMd5){
        $key = C('TOKEN_KEY');
        $accessKey = md5($fileMd5.$key);
        if($token == $accessKey){
            return true;
        }
        return false;
    }

}
