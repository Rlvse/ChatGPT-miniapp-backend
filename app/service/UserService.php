<?php

namespace app\service;

use app\contants\RedisKey;
use app\exception\Exception;
use app\util\RedisUtil;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

class UserService
{

    /**
     * :微信小程序自动登录
     */
    public static function login($params)
    {
        //接收code值
        $appId = self::getappid();
        $AppSecret = self::getappcret();
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$AppSecret}&js_code={$params['code']}&grant_type=authorization_code";
        $res = self::geturl($url);

        if (!$res) {
            new  Exception("login fail");
        }

        $openid = $res['openid'];

        // 获得openid
        $user = Db::name('user')->where('openid', $openid)->find();

        // 先查表 看有没有该id用户
        if ($user) {
            // JWT 生成Token TODO
            return $user['id'];
        } else {
            // $profile = self::getProfile($openid);
            //添加
            return Db::name('user')->insertGetId(
                [
                    'openid' => $openid,
                    'nick_name' => $params['nickname'] ?? null,
                    'avatar' => $params['avatar'] ?? null,
                    'createtime' => time(),
                ]
            );
        }
    }

    public static function getProfile($openid)
    {
        $access_token = self::adpatgetaccesstoken();
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $access_token . "&openid=" . $openid . "&lang=zh_CN";

        $result = self::getUrl($url);//获取公众号/小程序openid的地址
        $nickname = $result['nickname'];
        $headimgurl = $result['headimgurl'];
        return ['nickname' => $nickname, 'avatar' => $headimgurl];
    }

    private static function adpatgetaccesstoken()
    {
        // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
        $data = Cache::get("multigen_access_token");
        if ($data) {
            if ($data['expire_time'] < time()) {
                return self::getaccesstoken();
            } else {
                // return $data['access_token'];
                return self::getaccesstoken();
            }
        } else {
            return self::getaccesstoken();
        }
    }

    private static function getaccesstoken()
    {
        $appId = self::getappid();
        $AppSecret = self::getappcret();

        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appId . "&secret=" . $AppSecret;
        $res = self::getUrl($url);
        $access_token = $res['access_token'] ?? null;
        $result = null;
        if ($access_token) {
            Cache::set('multigen_access_token', ['expire_time' => time() + 7000, 'access_token' => $access_token]);
            $result = $access_token;
        } else {
            Log::info("获取access_token失败。" . json_encode($access_token, JSON_UNESCAPED_UNICODE));
        }

        Log::info("access_token:" . $result);

        return $result;
    }

    /**
     * get处理
     * @param $url
     * @return mixed
     */
    public static function getUrl($url)
    {
        $headerArray = array("Content-type:application/json;", "Accept:application/json");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        $output = curl_exec($ch);
        curl_close($ch);
        $output = json_decode($output, true);
        return $output;
    }

    private static function getappid()
    {
        $config = Config::get('custom');
        return $config['app_id'];
    }

    private static function getappcret()
    {
        $config = Config::get('custom');
        return $config['app_secret'];
    }

    public static function gettime()
    {
        $var1 = Cache::get(RedisKey::VISIT_TIME_KEY);
        $var2 = Cache::get(RedisKey::REQUEST_SUCCESS_KEY);
        $var3 = Cache::get(RedisKey::REQUEST_FAIL_KEY);
        return [
            'sum' => $var1,
            'success' => $var2,
            'fail' => $var3,
        ];
    }

    private static function addTime($key)
    {
        RedisUtil::addTime($key);
        $keyNew = str_replace(':', '', $key);

        $data = Db::name('sys')->where('key', $keyNew)->find();
        if (!$data) {
            Db::name('sys')->insert([
                'key' => $keyNew,
                'value' => 1,
            ]);
            return;
        }
        $value = $data['value'];
        $valueNew = $value + 1;
        Db::name('sys')->where('key', $keyNew)->update(['value' => $valueNew]);
    }

    private static function addLog($scene, $content = [], $text = '')
    {
        // log
        $ip = UserService::getip() ?? null;
        Db::name('log')->insert([
            'ip' => $ip,
            'scene' => $scene,
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'result' => $text,
            'createtime' => time()
        ]);
    }

    public static function getip()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {

            $ip = $_SERVER['HTTP_CDN_SRC_IP'];

        } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match(' /^([0 - 9]{1,3}\.){
                3}[0 - 9]{
                1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {

            $ip = $_SERVER['HTTP_CLIENT_IP'];

        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {

            foreach ($matches[0] as $xip) {

                if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                    $ip = $xip;
                    break;
                }
            }
        }
        return $ip;
    }
}