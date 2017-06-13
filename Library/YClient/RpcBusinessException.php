<?php
/**
 * WebService 端业务异常类.
 */

namespace YClient;

class RpcBusinessException extends \Exception
{
    private $errors;

    /**
     * 构造业务异常类.
     *
     * @param string|array $message 错误消息字符串, 或者多字段的错误 key/values 对,
     *                              values 可以为字符串或 int 型错误代码.
     * @param int $code 可选, 当 $message 为字符串时, 可以制定其 int 型错误代码.
     *
     * @example
     *
     *      use \Core\Lib\RpcBusinessException;
     *
     *      # case 1
     *      throw new RpcBusinessException('错误信息');
     *
     *      # case 2
     *      throw new RpcBusinessException('错误信息', 100);
     *
     *      # case 3
     *      throw new RpcBusinessException(array(
     *          'username' => '用户名不存在',
     *          'password' => '密码不正确',
     *      ));
     *
     *      # case 4
     *      throw new RpcBusinessException(array(
     *          'username' => 2001,
     *          'password' => 2002,
     *      ));
     */
    public function __construct($message, $code = 0)
    {
        $args = func_get_args();

        if (is_array($message)) {
            if (empty($message)) {
                throw new \Exception('You won\'t throw RpcBusinessException with an empty array.');
            }
            $this->errors = $message;
            $args[0] = 'Business Errors';
        }

        call_user_func_array(array($this, 'parent::__construct'), $args);
    }

    /**
     * 检查是否为错误 key/values 对.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * 返回错误 key/values 对.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
