<?php

namespace app\service;

use app\contants\ModeConstant;
use app\contants\RedisKey;
use app\exception\Exception;
use app\util\RedisUtil;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

class ApiService
{

    public static function chat($post)
    {

        self::addTime(RedisKey::VISIT_TIME_KEY);

        $content = $post['text'];
        $u = $post['u'] ?? 0;

        if (Cache::has("mutilgen:chat:user:" . $u)) {
            new Exception("error");
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::getapi(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                "messages" => [["role" => "user", "content" => $content]]
            )),
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . self::getkey(),
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        $complete = json_decode($response);

        if (!$complete || $err) {
            Log::error('请求失败');
            self::addTime(RedisKey::REQUEST_FAIL_KEY);
            new Exception('访问人数较多，请稍后重试');
        }

        if (isset($complete->choices[0]->message->content)) {
            $text = trim(str_replace("\\n", "\n", $complete->choices[0]->message->content), "\n");
        } elseif (isset($complete->error->message)) {
            $text = "api请求错误：" . $complete->error->message;
            self::addTime(RedisKey::REQUEST_FAIL_KEY);
        } else {
            $text = "服务器超时,或返回异常消息";
            self::addTime(RedisKey::REQUEST_FAIL_KEY);
        }

        self::addChatLog($u, $post, $text);
        Cache::set("mutilgen:chat:user:" . $u, 1, 10);

        return $text;
    }

    public static function do($post)
    {
        $scene = $post['scene'];
        if (!$scene) {
            new Exception('error');
        }

        $u = $post['u'] ?? -1;

        self::addTime(RedisKey::VISIT_TIME_KEY);

        $content = self::packContent($post, $scene);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::getapi(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                "messages" => [["role" => "user", "content" => $content]]
            )),
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . self::getkey(),
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        $complete = json_decode($response);

        if (!$complete || $err) {
            Log::error('请求失败');
            self::addTime(RedisKey::REQUEST_FAIL_KEY);
            new Exception('访问人数较多，请稍后重试');
        }

        if (isset($complete->choices[0]->message->content)) {
            $text = trim(str_replace("\\n", "\n", $complete->choices[0]->message->content), "\n");
        } elseif (isset($complete->error->message)) {
            $text = "api请求错误：" . $complete->error->message;
            self::addTime(RedisKey::REQUEST_FAIL_KEY);
        } else {
            $text = "服务器超时,或返回异常消息";
            self::addTime(RedisKey::REQUEST_FAIL_KEY);
        }

        self::addTime(RedisKey::REQUEST_SUCCESS_KEY);
        self::addLog($u, $scene, $post, $text);

        return $text;
    }

    private static function packContent($post, $mode = ModeConstant::MODE_CHAT)
    {
        switch ($mode) {
            case ModeConstant::MODE_CHAT:
                return self::packSimple($post);
            case ModeConstant::MODE_RIBAOZHOUBAO:
                return self::packRibaozhoubao($post);
            case ModeConstant::MODE_KENDEJIV50:
                return self::packKendejiv50($post);
            case ModeConstant::MODE_WENZHANG_FRAMEWORK:
                return self::packWenzhangFramework($post);
            case ModeConstant::MODE_WENAN:
                return self::packWenan($post);
            case ModeConstant::MODE_JIANTAOSHU:
                return self::packJiantaoshu($post);
            case ModeConstant::MODE_FANGAN:
                return self::packFangan($post);
            case ModeConstant::MODE_LIYOU:
                return self::packLiyou($post);
            case ModeConstant::MODE_PINGJIA:
                return self::packPingjia($post);
            case ModeConstant::MODE_GERENZONGJIE:
                return self::packGerenzongjie($post);
            case ModeConstant::MODE_HAOWUMIAOSHU:
                return self::packHaowumiaoshu($post);
            case ModeConstant::MODE_CODE:
                return self::packCode($post);
            case ModeConstant::MODE_STORE:
                return self::packStory($post);
            default:
                new Exception("访问人数较大，请稍后重试");
        }
        return null;
    }

    private static function packSimple($post)
    {
        $content = $post['text'] ?? '';
        if (!$content) {
            new Exception("missing parameter", 500, 500);
        }

        return $content;
    }

    /**
     * 生成周报日报文案
     * @param $post
     * @return string
     */
    private static function packRibaozhoubao($post)
    {
        $text = $post['text'] ?? null;
        $word = $post['word'] ?? 50;
        $job = $post['job'] ?? null;
        $type = $post['type'] ?? 0;

        if (!$job) {
            new Exception("missing parameter", 500, 500);
        }

        $typeNew = $type == 0 ? '每日工作汇报' : '每周工作汇报';
        $no_key_source = [
            '我是一名' . $job . ',请为我写一份大约' . $word . '字的工作' . $typeNew,
        ];

        $key_source = [
            '请以"' . $text . '"为主题，为' . $job . '写一份大约' . $word . '字的工作' . $typeNew,
        ];

        if ($text) {
            $source = $key_source;
        } else {
            $source = $no_key_source;
        }

        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 生成文案
     * @param $post
     * @return string
     */
    private static function packWenan($post)
    {
        $text = $post['text'] ?? null;
        $type = $post['type'] ?? 0;
        $style = $post['style'] ?? 0;

        if (!$text) {
            new Exception("请输入关键字");
        }

        // 文字限制
        $words = '100-200';

        // 小红书风格
        if ($style == 0) {
            if ($type == 0) {
                $key_source = [
                    '我需要你扮演一个标题生成器，请为我写3个关于' . $text . '的热门标题，带与主题相关的emoji表情，每个标题不超过20个字',
                ];
            } else {
                $key_source = [
                    '小红书的文案风格是: 每一段前面加一个emoji，最后加一些tag。可以自动换行，全篇内容字数在' . $words . '字之间。写一篇关于' . $text . '的文章',
                ];
            }
        } else if ($style == 3) {// 官方
            if ($type == 0) {
                $key_source = [
                    '请你以官方、报刊、汇报稿件的文笔风格，为我生成3个关于' . $text . '的文章标题，每个标题字数在20以内',
                ];
            } else {
                $key_source = [
                    '请你以官方、报刊、汇报稿件的文笔风格，为我写一篇字数在' . $words . '以内，关于' . $text . '的文章',
                ];
            }
        } else {// 抖音+微博
            if ($type == 0) {
                $key_source = [
                    '我想让你扮演一个标题生成器。我会给你提供一个主题，你会生成3个吸引眼球的标题，每个标题不超过20个字。我的第一个主题是:' . $text,
                ];
            } else {
                $key_source = [
                    '我想让你扮演一个文章生成器。写一篇关于' . $text . '的文章，全篇内容字数在' . $words . '字之间。',
                ];
            }
        }

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 好物描述
     * @param $post
     * @return string
     */
    private static function packHaowumiaoshu($post)
    {
        $text = $post['text'] ?? null;
        $type = $post['type'] ?? 0;

        $words = '100-200';
        // 小红书
        if ($type == 0) {
            $key_source = [
                '我想让你扮演小红书的文案生成器。请你为我生成一份关于赞美，推荐' . $text . '的描述内容，字数范围是' . $words . '字之间，内容中可带上一些与主题有关的emoji',
            ];
        } else {
            $key_source = [
                '请帮我写一篇赞美，推荐' . $text . '的文章，全篇内容字数在' . $words . '字之间，口吻有趣、幽默。'
            ];
        }

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 代码助手
     * @param $post
     * @return string
     */
    private static function packCode($post)
    {
        $text = $post['text'] ?? null;
        $type = $post['type'] ?? 0;
        $lan = $post['lan'] ?? 0;

        if ($lan == 0) {
            $lanNew = 'Java';
        } else if ($lan == 1) {
            $lanNew = 'PHP';
        } else if ($lan == 2) {
            $lanNew = 'JS';
        } else if ($lan == 3) {
            $lanNew = 'Python';
        } else {
            $lanNew = 'C';
        }

        // 小红书
        if ($type == 0) {
            $key_source = [
                '帮我使用' . $lanNew . '语言写一段代码，内容是：' . $text,
            ];
        } else {
            $key_source = [
                '请帮我检查一下以下这段' . $lanNew . '语言代码中存在的bug：' . $text
            ];
        }

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 哄人小故事
     * @param $post
     * @return string
     */
    private static function packStory($post)
    {
        $text = $post['text'] ?? null;

        $words = '100-200';
        if ($text) {
            $key_source = [
                '请为我写一个字数范围在' . $words . '的小故事，故事内容关于' . $text,
            ];
        } else {
            $key_source = [
                '请为我写一个字数范围在' . $words . '的小故事，风格要可爱，轻松，愉快，治愈',
            ];
        }

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 个人总结
     * @param $post
     * @return string
     */
    private static function packGerenzongjie($post)
    {
        $text = $post['text'] ?? null;
        $type = $post['type'] ?? 0;

        if ($type == 0) {
            $typeNew = '学生干部';
        } else {
            $typeNew = '职场人';
        }

        $words = '200-300';
        $key_source = [
            '我是一名' . $typeNew . '，请帮我写一份以' . $text . '为主题的个人自我评价，字数范围是' . $words,
        ];

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 评价
     * @param $post
     * @return string
     */
    private static function packPingjia($post)
    {
        $text = $post['text'] ?? null;
        $mode = $post['mode'] ?? 0;

        if ($mode == 0) {
            $typeNew = '好评';
        } else {
            $typeNew = '差评';
        }

        $words = '100-200';
        $key_source = [
            '请写一份关于' . $text . '的评价，必须是' . $typeNew . '，风格活泼、生动，字数范围在' . $words,
        ];

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 理由
     * @param $post
     * @return string
     */
    private static function packLiyou($post)
    {
        $object = $post['object'] ?? 0;
        $reason = $post['reason'] ?? 0;

        if ($object == 0) {
            $objectNew = '老板';
            $begin = '尊敬的老板';
        } else if ($object == 1) {
            $objectNew = '老师';
            $begin = '亲爱的老师';
        } else if ($object == 2) {
            $objectNew = '客户';
            $begin = '客户您好';
        } else if ($object == 3) {
            $objectNew = '男 / 女朋友';
            $begin = '我的宝贝';
        } else {
            $objectNew = '单位';
            $begin = '各位同事';
        }

        if ($reason == 1) {
            $reasonNew = '生病';
        } else if ($object == 2) {
            $reasonNew = '培训';
        } else if ($object == 3) {
            $reasonNew = '看牙';
        } else if ($object == 4) {
            $reasonNew = '装修';
        } else if ($object == 5) {
            $reasonNew = '搬家';
        } else {
            $reasonNew = '结婚';
        }

        if ($reason == 0) {
            $key_source = [
                '请为我写一段向我的' . $objectNew . '请假说明的理由，开头是：' . $begin . '，字数在50 - 100个以内',
            ];
        } else {
            $key_source = [
                '请为想一段向我的' . $objectNew . '请假的理由，原因是' . $reasonNew . '，开头是：' . $begin . '，字数在50 - 100个以内',
            ];
        }

        $source = $key_source;

        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 检讨书
     * @param $post
     * @return string
     */
    private static function packJiantaoshu($post)
    {
        $text = $post['text'] ?? null;
        $object = $post['object'] ?? 0;

        if (!$text) {
            new Exception("请输入关键字");
        }

        $words = '100-200';
        if ($object == 0) {
            $objectNew = '老板';
            $begin = '尊敬的老板';
        } else if ($object == 1) {
            $objectNew = '男/女朋友';
            $begin = '我的宝贝';
        } else if ($object == 2) {
            $objectNew = '朋友';
            $begin = '我的宝贝';
        } else {
            $objectNew = '单位';
            $begin = '各位领导，同事';
        }

        $key_source = [
            '我因为' . $text . '的原因犯了错误，麻烦帮我向我的' . $objectNew . '写一份检讨书，字数' . $words . '字左右，开头是' . $begin . '，内容需要诚恳，有礼貌，中间需要有个人反思的部分，最后加上承诺，比说明用实际行动证明',
        ];

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 方案
     * @param $post
     * @return string
     */
    private static function packFangan($post)
    {
        $text = $post['text'] ?? null;

        if (!$text) {
            new Exception("请输入关键字");
        }

        $words = '300-500';

        $key_source = [
            '请我写一份关于：' . $text . '的执行方案，需要分点编写，字数范围大概在' . $words
        ];

        $source = $key_source;
        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 生成文章框架
     * @param $post
     * @return string
     */
    private static function packWenzhangFramework($post)
    {
        $text = $post['text'] ?? null;
        $type = $post['type'] ?? 0;

        if ($type == 0) {
            $typeNew = '汇报';
            $role = '汇报稿件编写者';
        } else if ($type == 1) {
            $typeNew = '论文';
            $role = '论文作者';
        } else {
            $typeNew = '笔记';
            $role = '笔记文章作者';
        }

        $key_source = [
            '你的一名专业的' . $role . '，请你对' . $text . '展开分析，写一份关于' . $text . '的' . $typeNew . '类型中文大纲',
        ];


        $source = $key_source;

        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    /**
     * 生成肯德基v50文案
     * @param $post
     * @return string
     */
    private static function packKendejiv50($post)
    {
        $text = $post['text'] ?? null;
        $word = $post['word'] ?? 50;

        $no_key_source = [
            '请为我编一个' . $word . '字的故事，故事最后需要以"能v我50吃肯德基疯狂星期四吗？"结尾',
        ];

        $key_source = [
            '请以"' . $text . '"为主题，为我编一个' . $word . '字的故事，故事最后需要以"所以你能v我50吃肯德基疯狂星期四吗？"结尾',
        ];

        if ($text) {
            $source = $key_source;
        } else {
            $source = $no_key_source;
        }

        $rand = rand(0, count($source) - 1);

        return $source[$rand];
    }

    private static function getapi()
    {
        $config = Config::get('custom');
        return $config['api'];
    }

    private static function getkey()
    {
        $config = Config::get('custom');
        $api_keys = $config['api_keys'];
        $rand = rand(0, count($api_keys) - 1);
        return $api_keys[$rand];
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

    private static function addLog($u, $scene, $content = [], $text = '')
    {
        $user = Db::name('user')->where('id', $u)->find();
        $ip = ApiService::getip() ?? null;

        if (!$user) {
            $arr = [
                'ip' => $ip,
                'scene' => $scene,
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'result' => $text,
                'createtime' => time()
            ];
        } else {
            $arr = [
                'ip' => $ip,
                'uid' => $u,
                'nick_name' => $user['nick_name'],
                'avatar' => $user['avatar'],
                'scene' => $scene,
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'result' => $text,
                'createtime' => time()
            ];

            $num = $user['num'] + 1;
            Db::name('user')->update([
                'id' => $u,
                'num' => $num
            ]);
        }
        // log
        Db::name('log')->insert($arr);
    }

    private static function addChatLog($u, $content = [], $text = '')
    {
        $user = Db::name('user')->where('id', $u)->find();
        $ip = ApiService::getip() ?? null;

        if (!$user) {
            $arr = [
                'ip' => $ip,
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'result' => $text,
                'createtime' => time()
            ];
        } else {
            $arr = [
                'ip' => $ip,
                'uid' => $u,
                'nick_name' => $user['nick_name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'result' => $text,
                'createtime' => time()
            ];

            $num = $user['num'] + 1;
            Db::name('user')->update([
                'id' => $u,
                'num' => $num
            ]);
        }
        // log
        Db::name('chat_log')->insert($arr);
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

    public
    static function getnotices()
    {
        return Db::name("notice")->select();
    }

    public
    static function getmessages()
    {
        return Db::name("message")->select();
    }

    public
    static function getqrcode()
    {
        return Db::name("image")->order('id')->find();
    }

    public
    static function getreqdata()
    {
        $logCount = Db::name("log")->field('id')->count();
        $chatLogCount = Db::name("chat_log")->field('id')->count();
        return [
            'sum' => $logCount + $chatLogCount
        ];
    }

}