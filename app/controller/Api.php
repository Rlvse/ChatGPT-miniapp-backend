<?php

namespace app\controller;

use app\BaseController;
use app\exception\Exception;
use app\service\ApiService;

class Api extends BaseController
{
    public function do()
    {
        $result = ApiService::do($this->request->post());
        return $this->ok($result);
    }

    public function chat()
    {
        $result = ApiService::chat($this->request->post());
        return $this->ok($result);
    }

    public function getnotices()
    {
        return $this->ok(ApiService::getnotices());
    }

    public function getmessages()
    {
        return $this->ok(ApiService::getmessages());
    }
    public function getqrcode()
    {
        return $this->ok(ApiService::getqrcode());
    }

    public function getreqdata()
    {
        return $this->ok(ApiService::getreqdata());
    }

}
