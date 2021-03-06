<?php


namespace App\HttpController\Admin;


use App\Log\LogHandel;
use EasySwoole\Bridge\Exception;
use EasySwoole\Http\AbstractInterface\Controller;

class Base extends Controller
{


    protected function onRequest(?string $action): ?bool
    {

        return parent::onRequest($action); // TODO: Change the autogenerated stub
    }


    function GetUidFormURL($url)
    {

        $log = new LogHandel();
        try {

//            var_dump($url);
            if (!$url || $url == "" || empty($url)) {
                return false;
            }
            $url = trim($url);
            #new 一个对象
            $client = new \EasySwoole\HttpClient\HttpClient($url);
            #禁止重定向
            $client->enableFollowLocation(0);
            #正则 id
            $pattern = "/(@.*)\/video\/(\d{19})/";
            $response = $client->get();
            if (!$response) {
                var_dump($response);
                $log->log('获取抖音的url id 失败 $response 返回为false');
                return false;
            }


            $data = $response->getBody();
            var_dump($data);
            if (!$data) {
                var_dump($data);
                $log->log('获取抖音的url id 失败 $data 返回为false');
                return false;
            }
            preg_match($pattern, $data, $match);

//            var_dump($match);

            if (!isset($match[0])) {
                $log->log('获取抖音的url id 失败');
                return false;
            }
            return $match;
        } catch (\Throwable $exception) {
            $log->log('获取抖音的url id 异常' . $exception);
            var_dump($exception);
            return false;
        }


    }


}