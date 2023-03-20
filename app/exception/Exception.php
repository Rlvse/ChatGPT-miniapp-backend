<?php

namespace app\exception;

use think\Response;

class Exception
{
    protected $msg = '错误消息';
    protected $code = 0;  //TOAST自动弹出消息
    const NOT_LOGIN = 401; //未登录自动弹框提醒
    const NOT_AUTHORIZE = 403;//
    const IGNORE = -1;

    public function __construct($msg, $code = 0, $status_code = 200)
    {
        $this->msg = $msg;
        $this->code = $code;
        $this->send($status_code);
    }

    protected function send($code = 200)
    {
        $data = [
            'code' => $this->code,
            'msg' => $this->msg,
            'data' => null,
            'time' => time()
        ];
        $response = Response::create($data, 'json', $code);
        throw new \think\exception\HttpResponseException($response);
    }
}
