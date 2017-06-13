<?php
/**
 * SystemLog.
 *
 * @author taozywu <tao.wu@zerotech.com>
 * @date 2016/10/20
 */

namespace Custom;


class SystemLog {

    /**
     * 写入日志.
     *
     * @param string  $message 错误信息.
     * @param integer $level   错误级别.
     */
    public function write($message, $level){
        switch ($level){
            case 1:
                $header = 'PHP Emergency:  ';
                break;
            case 2:
                $header = 'PHP Alert:  ';
                break;
            case 3:
                $header = 'PHP Critical:  ';
                break;
            case 4:
                $header = 'PHP Fatal error:  ';
                break;
            case 5:
                $header = 'PHP Warning:  ';
                break;
            case 6:
                $header = 'PHP Notice:  ';
                break;
            case 7:
                $header = 'PHP Info:  ';
                break;
            case 8:
                $header = 'PHP Debug:  ';
                break;
            default:
                $header = 'PHP Other:  ';
                break;
        }
        error_log($header.$message);
    }

}
