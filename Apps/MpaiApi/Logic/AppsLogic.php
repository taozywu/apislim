<?php

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

    /**
     * 处理版本数据.
     * 
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    private function dealData($data)
    {
        $data['storeurl'] = $this->getUrl($data['storeurl']);
        $data['versionCode'] = $data['versioncode'];
        $data['versionName'] = $data['version'];
        $data['type'] = $data['types'];
        $data['records'] = $this->getRecords($data['records']);
        unset($data['id'], $data['create_time'], $data['pid'], $data['version']);
        unset($data['versioncode'], $data['status'], $data['types']);
        unset($data['qzid'], $data['otype'], $data['upobject'], $data['vno']);
        unset($data['limit_plat'], $data['min_version'], $data['max_version']);
        return $data;
    }

    /**
     * 获取URL.
     * 
     * @param  [type] $storeurl [description]
     * @return [type]           [description]
     */
    private function getUrl($storeurl)
    {
        $md5Url = md5($storeurl);
        $key = "ZERO_MPAI_URL:{$md5Url}";
        $redis = \Common\Redis\CRedis::cache('default');
        $url = $redis->get($key);
        if ($url) {
             return $url;
        }
        $arr = @explode('@', $storeurl);
        $count = count($arr);
        if ($count > 1) {
            $or = "";
            $arrstr = "";
            for ($i = 1; $i < $count; $i ++) {
                $arrstr .= "{$or}".$arr[$i];
                $or = "/";
            }
            $url = get_down_file_url2($arr['0'], $arrstr, C("urlExpire"));
        } else {
            $url = $storeurl;
        }
        unset($arr);
        $redis->setex($key, C("urlExpire"), $url);
        return $url;
    }

    /**
     * 获取更新日志.
     * 
     * @param  [type] $records [description]
     * @return [type]          [description]
     */
    private function getRecords($records)
    {
        $records = $records ? json_decode($records,true) : null;
        // 如果升级描述中未找到某个语言的描述 则默认给英文.
        return isset($records[C("LANNAMENEW")])?$records[C("LANNAMENEW")]:$records['en'];
    }

    /**
     * 获取OTA的升级包信息{暂时没考虑差量和强升}.
     * 
     * @param  [type] $qzId        [description]
     * @param  [type] $objType     [description]
     * @param  [type] $upObject    [description]
     * @param  [type] $vName       [description]
     * @param  array  $lParams     [description]
     * @return [type]              [description]
     */
    public function getOTAData($qzId, $objType, $upObject, $vName = null, array $lParams = array())
    {
        $map = array(
            "qzid" => $qzId,
            "otype" => $objType,
            "upobject" => $upObject,
            "types" => 0,
            "status" => 1,
        );
        if ($lParams) {
            if (isset($lParams['limit_plat'])) $map['limit_plat'] = (int) $lParams['limit_plat'];
        }
        // 获取最新一条的整包数据.
        $pData = M("Dispersion")->findFieldOrder($map, null, "id desc limit 1");
        if (!$pData) {
            return null;
        }
        if (!$pData['min_version'] && !$pData['max_version']) {
            return $this->dealData($pData);
        }
        if ($pData['min_version'] && $pData['min_version'] > $lParams['version']) {
            return null;
        }
        if ($pData['max_version'] && $pData['max_version'] < $lParams['version']) {
            return null;
        }
        return $this->dealData($pData);
    }

    /**
     * 获取Android的升级包信息{此处不考虑强升}.
     * 
     * @param  [type] $qzId        [description]
     * @param  [type] $objType     [description]
     * @param  [type] $upObject    [description]
     * @param  [type] $vName       [description]
     * @return [type]              [description]
     */
    public function getAppData($qzId, $objType, $upObject, $vName = null)
    {
        $map = array(
            "qzid" => $qzId,
            "otype" => $objType,
            "upobject" => $upObject,
            "types" => 0,
            "status" => 1,
        );
        // 获取最新一条的整包数据.
        $pData = M("Dispersion")->findFieldOrder($map, null, "id desc");
        if (!$pData) {
            return null;
        }
        if (!$vName) {
            return $this->dealData($pData);
        }
        // 获取该整包下的子包的数据.
        $map['pid'] = $pData['id'];
        $map['types'] = 1;
        $map['vno'] = $vName;
        $sData = M("Dispersion")->findFieldOrder($map, null, "id desc");
        // 判断子包不存在
        if (!$sData) {
            return $this->dealData($pData);
        }
        unset($pData, $map);
        return $this->dealData($sData);
    }

    /**
     * 获取固件差量升级数据.
     * 
     * @param  [type] $qzId     [description]
     * @param  [type] $objType  [description]
     * @param  [type] $upObject [description]
     * @param  [type] $vName    [description]
     * @return [type]           [description]
     */
    public function getGjData($qzId, $objType, $upObject, $vName = null)
    {
        // 先获取最新的固件映射数据.
        $pData = \YClient\Text::inst("ZeroMpai")->setClass("Dispersion")->getFwareData($qzId, $objType);
        if (!$pData) {
            // 如果没取到固件映射的数 则直接给一个默认的数据.
            $gujianConfs = C("GUJIAN");
            $gujianConf = $qzId < 2 ? $gujianConfs['online'] : $gujianConfs['test'];
            $minVersion = $gujianConf['min_version'];
            unset($gujianConfs, $gujianConf);
            return $this->dealData(
                $this->dealGjData($qzId, $objType, $upObject, array("vno"=> "ABCD", "vname" => $minVersion))
            );
        }
        if (!$vName) {
            goto dealGjData;
        }
        $sData = \YClient\Text::inst("ZeroMpai")->setClass("Dispersion")->getFwareData($qzId, $objType, $vName);
        if (!$sData) {
            goto dealGjData;
        }
        return $this->dealData($this->dealGjData($qzId, $objType, $upObject, $this->dealGjSData($pData, $sData)));
        
        dealGjData:
        return $this->dealData($this->dealGjData($qzId, $objType, $upObject, $this->dealGjPData($pData)));
    }

    /**
     * 处理并获固件数据.
     * 
     * @param  [type] $qzId     [description]
     * @param  [type] $objType  [description]
     * @param  [type] $upObject [description]
     * @param  array  $vData    [description]
     * @return [type]           [description]
     */
    private function dealGjData($qzId, $objType, $upObject, array $vData)
    {
        $params = array(
            "qzid" => $qzId,
            "otype" => $objType,
            "upobject" => $upObject,
            "vno" => $vData['vno'],
            "version" => $vData['vname'],
        );
        return \YClient\Text::inst("ZeroMpai")->setClass("Dispersion")->findFieldOrder(
            $params, null, "id desc"
        );
    }

    /**
     * 处理固件父级映射数据.
     * 
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    private function dealGjPData(array $data)
    {
        return array(
            "vno" => $data['vno'],
            "vname" => $data['pvname'],
        );
    }

    /**
     * 处理父级和子级映射.
     * 
     * @param  array  $pData [description]
     * @param  array  $sData [description]
     * @return [type]        [description]
     */
    private function dealGjSData(array $pData, array $sData)
    {
        $pDataArr = $this->dealGjVData($pData);
        $sDataArr = $this->dealGjVData($sData);

        $vstrno = "";
        $vname = $pData['pvname'];
        foreach ($pDataArr as $pk => $pv) {
            if (!isset($sDataArr[$pk])) {
                $vstrno .= $pk;
            }
            if ($pv !== $sDataArr[$pk]) {
                $vstrno .= $pk;
            }
        }
        unset($pDataArr, $sDataArr, $pData, $sData);
        return array(
            "vno" => $vstrno ? $vstrno : "ABCD",
            "vname" => $vname,
        );
    }

    /**
     * 处理固件映射整理.
     * 
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    private function dealGjVData(array $data)
    {
        $result = array();
        if (!$data || !$data['node']) {
            return $result;
        }
        foreach ($data['node'] as $p) {
            $result[$p['vno']] = $p['vcode'];
        }
        unset($data);
        return $result;
    }
}
