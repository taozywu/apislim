<?php
/**
 * Route.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */

namespace Custom;

use \Rest;

class Route {

    // 权限配置.
    private static $config = array();

    private static $instances = null;
    // 控制器
    public static $controller = null;

    public static $site = null;

    private static $version = array();

    public function __construct()
    {
        $class = SITE . '\Config\AclConfig';
        self::$config = (array) new $class;
        $this->getController();
    }

    /**
     * 实例化类.
     *
     * @return Route|null
     */
    public static function instances()
    {
        if(self::$instances instanceof Route){
            return self::$instances;
        }
        self::$instances = new self();
        return self::$instances;
    }

    /**
     * 获取method  GET  POST PUT DELETE
     *
     * @return string
     */
    public function getMethod()
    {
        return Rest::$slim->request()->getMethod();
    }

    /**
     *  获取uri
     *
     * @return string
     */
    public function getResourceUri()
    {
        $url = Rest::$slim->request()->getResourceUri();
	    return $url ? rtrim($url, "/") : "";
    }

    /**
     * 获取控制器名.
     *
     */
    private function getController()
    {
        $uri= $this->getResourceUri();
        $uriArr = explode('/',ltrim($uri,'/'));
        self::$controller = array_shift($uriArr);
        if(!self::$controller ){
            self::$controller = Rest::$config['default']['controller'];
        }
    }

    /**
     * 检查路由是否存在.
     *
     * @return bool
     * @throws \Exception
     */
    public function checkUri()
    {
        $method = Rest::$slim->request()->getMethod();
        if($method == 'OPTIONS'){
            return true;
        }
        if(!array_key_exists(self::$controller,self::$config)){
            Rest::$slim->notFound(function(){
                echo '{"code":10,"msg":"System error: Route is not configured"}';
            });
            Rest::$slim->notFound();
        }

        $pattern = self::$config[self::$controller];
        if(!array_key_exists($method,$pattern)){
            Rest::$slim->notFound(function(){
                echo '{"code":10,"msg":"System error: Routing mode is not configured"}';
            });
            Rest::$slim->notFound();
        }
        // 初始化验证路由
        $route = new \Slim\Route(null, function () {});
	
        foreach($pattern[$method] as $key => $val){
            $route->setPattern($key);
            if($route->matches($this->getResourceUri())){
                self::$site = (array_key_exists('site',$val) ? $val['site'] : '');
                self::$version = (array_key_exists('v',$val) ? $val['v'] : array());
                return true;
            }
        }
        Rest::$slim->notFound(function(){
            echo '{"code":10,"msg":"System error: Route authentication failed"}';
        });
        Rest::$slim->notFound();
    }

    /**
     * 创建controller
     *
     */
    public static function createController()
    {
        $site = SITE;
        if(self::$site){
            $site = self::$site;
        }

        // 判断使用的是否是老版本数据
        $version = self::getVersion();
        if($version){
            $class = $site.'\\Controller\\V'.str_replace('.','_',$version).'\\'.ucfirst(self::$controller);
            $path = DIRECTORY_SEPARATOR.ltrim(str_replace('\\',DIRECTORY_SEPARATOR,$class),DIRECTORY_SEPARATOR);
            if(!is_file(ROOT."/Apps".$path.'.php')){
                $class = $site.'\\Controller\\'.ucfirst(self::$controller);
            }
        }else{
            $class = $site.'\\Controller\\'.ucfirst(self::$controller);
        }

        $objController = new $class;
        //检查自动运行的方法是否存在
        if(method_exists($objController,'init')){
            $objController->init();
        }
        // 创建验证.
        if(method_exists($objController,'initRule')){
            $objController->initRule();
        }
    }

    /**
     * 获取需要访问的版本号.
     *
     * @return boolean|string
     */
    private static function getVersion()
    {
        // 检测版本是否存在
        if(array_key_exists('v',$_GET)){
            $clientVersion = $_GET['v'];
        }else{
            $clientVersion = 0;
        }
        self::getV($clientVersion);
        if (!self::$version) {
            return false;
        }
        foreach(self::$version as $vk => $vv){
            if(version_compare(C("VERSION"),$vk,'<=')){
                return $vv;
            }
        }
        return false;
    }

    /**
     * 处理版本.
     * @return [type] [description]
     */
    private static function getV($clientVersion)
    {
        $langClass = SITE . '\Config\Lang';
        $langObj = new $langClass;

        $plat = $lan = $cliversion = $qzid = $mod = 0;
        // 兼容2代header
        if (!$clientVersion) {
            $header = Rest::$header;
            $clientVersion = isset($header['v']) ? $header['v'] : 0;
            unset($header);
        }
        // 兼容2代的v
        if (strpos($clientVersion, "-") !== false) {
            list($plat, $lan, $cliversion, $qzid, $mod) = explode("-", $clientVersion);
        } else {
            $cliversion = $clientVersion;
        }
    	// 检测位数并处理
    	if ($cliversion) {
            // 先排除是否有build版本
            if (strpos($cliversion, "_") !== false) {
                list($cliv, $buildv) = explode("_", $cliversion);
                $cliversion = $cliv;
            }
    	}
        $plat = (int) $plat;
        $lan = (int) $lan;
        $qzid = (int) $qzid;
        $mod = (int) $mod;
        $lanName = getLang($lan);
        $lanNameStr = lan2str($lan);
        $otypeName = (int) \Common\Tool::getOtype($mod);
        C("PLATFORM", $plat);
        C("LAN", $lan);
        C("LANNAME", $lanName?$lanName:"zhCn");
        C("LANNAMENEW", $lanNameStr);
        C("VERSION", $cliversion?$cliversion:'0');
	    C("ISNEWVERSION", $cliversion>=C("NEW_API_VERSION")?1:0);
        C("QZID", $qzid<2?0:$qzid);
        C("LANG", $langObj::$$lanName);
        C("OTYPE", $otypeName);
        C("OTYPEID", $mod);
    }
}
