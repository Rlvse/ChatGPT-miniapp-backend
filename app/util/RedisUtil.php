<?php

namespace app\util;

use app\contants\RedisKey;
use think\facade\Cache;

class RedisUtil
{
    public static function addTime($key)
    {
        $result = Cache::get($key);
        if (!$result) {
            Cache::set($key, 1);
        }
        $resultNew = $result + 1;
        Cache::set($key, $resultNew);
    }
}
