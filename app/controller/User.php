<?php

namespace app\controller;

use app\BaseController;
use app\service\ApiService;
use app\service\UserService;

class User extends BaseController
{
    public function login()
    {
        $result = UserService::login($this->request->post());
        return $this->ok($result);
    }
}
