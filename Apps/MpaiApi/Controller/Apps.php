<?php
/**
 * Apps
 *
 * author yanshanbo shaobo.yan@zerotech.com
 * date 2016/11/01
 */

namespace MpaiApi\Controller;

use \Rest;

class Apps extends BaseTp
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 路由初始化
     */
    public function init()
    {
        Rest::$slim->get('/apps/version',function() {
            $this->getVersion();
        });
    }

    /**
     * 获取升级包信息.
     * 
     * @return [type] [description]
     */
    public function getVersion()
    {
        $this->dump(1, array());
    }

}
