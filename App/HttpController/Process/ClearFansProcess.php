<?php


namespace App\HttpController\Process;


use App\HttpController\Model\MonitorFansModel;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;

/**
 * Class ClearFansProcess
 * @package App\HttpController\Process
 * 清除垃圾  粉丝进程  节省 空间
 */
class ClearFansProcess extends AbstractProcess
{

    protected function run($arg)
    {
        var_dump("ClearFansProcess is running");
        go(function () {
            while (true) {
                DbManager::getInstance()->invoke(function ($client) {
                    $res = MonitorFansModel::invoke($client)->limit(10000)->where('sex', NULL, 'IS')->all();
                    $redis = RedisPool::defer('redis');
                    foreach ($res as $re) {
                        preg_match_all('/x-expires=(\d{10})/', $re['image_url'], $pat_array);   //正则视频 id
                        if (!isset($pat_array[1][0]) || $pat_array[1][0] < time()) {
                            # 没有正则到 直接删除这条链接
                            MonitorFansModel::invoke($client)->destroy(['id' => $re['id']]);
                            $redis->del("Fans_" . $re['unique_id']);
                            continue;
                        }
                    }
                    #第二部
                    $res = MonitorFansModel::invoke($client)->limit(10000)->where(['sex' => ''])->all();
                    foreach ($res as $re) {
                        MonitorFansModel::invoke($client)->destroy(['id' => $re['id']]);
                    }
                });
                \co::sleep(1800);  //20秒采集一
            }
        });
    }
}