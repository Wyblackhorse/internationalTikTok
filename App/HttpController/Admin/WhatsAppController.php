<?php


namespace App\HttpController\Admin;


use App\HttpController\Model\MonitorVideoModel;
use App\HttpController\Model\WhatsAppModel;
use App\HttpController\Task\SetWhatsAppPhoneTask;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\RedisPool\RedisPool;

/***
 * Class WhatsAppController
 * @package App\HttpController\Admin
 * WhatsApps  app
 *
 */
class WhatsAppController extends Base
{


    /**
     * @return bool  1 未审核 2审核未使用 3审核已使用 4 无效  5 删除
     */
    function getPhone()
    {
        try {
            $action = $this->request()->getQueryParam('action');

            if ($action == "phone") {  # 手机去获取手机号  redis 参与  1 未审核 2审核未使用 3审核已使用
                $redis = RedisPool::defer('redis');
                $nickname = $this->request()->getQueryParam('nickname');
                $username = $this->request()->getQueryParam('username');
                if (isset($username) && $username != -1) {
                    $res = WhatsAppModel::create()->get(['status' => 2, 'username' => $username]);
                } else {
                    $res = WhatsAppModel::create()->get(['status' => 2]);
                }


                if ($res) {
                    if ($redis->get("WhatsApp_" . $res['phone'])) {
                        $this->writeJson(-101, [], "没有手机号");
                        return false;
                    }
                    $redis->set("WhatsApp_" . $res['phone'], "status");
                    WhatsAppModel::create()->where(['id' => $res['id']])->update(['status' => 3, 'nickname' => $nickname, 'extract_time' => time()]);
                    $this->writeJson(200, [], $res['phone']);
                    return false;
                }
                $this->writeJson(-101, [], "没有手机号");
                return false;
            }


            if ($action == "check") {   //审核
                $status = $this->request()->getQueryParam('status');
                $id = $this->request()->getQueryParam('id');
                if (!isset($id)) {
                    $this->writeJson(-101, [], "非法请求");
                    return false;
                }
                WhatsAppModel::create()->where(['id' => $id])->update(['status' => $status]);
                $this->writeJson(200, [], "审核成功");
                return false;
            }


            if ($action == "GET") {
                $page = $this->request()->getRequestParam('page');         // 当前页码
                $limit = $this->request()->getRequestParam('limit');        // 每页多少条数据
                $status = $this->request()->getRequestParam('status');
                $country = $this->request()->getQueryParam('country');
                $username = $this->request()->getQueryParam('username');
                $nickname = $this->request()->getQueryParam('nickname');
                #check_time  extract_time
                $check_timeB = $this->request()->getQueryParam('check_timeB');
                $check_timeD = $this->request()->getQueryParam('check_timeD');
                $extract_timeB = $this->request()->getQueryParam('extract_timeB');
                $extract_timeD = $this->request()->getQueryParam('extract_timeD');


                $model = WhatsAppModel::create()->limit($limit * ($page - 1), $limit)->withTotalCount()->order('created', 'ASC');
                if (isset($country) && !empty($country)) {
                    $model = $model->where(['country' => $country]);
                }
                if (isset($username) && !empty($username)) {
                    $model = $model->where(['username' => $username]);
                }

                if (isset($nickname) && !empty($nickname)) {
                    $model = $model->where(['nickname' =>$nickname]);
                }
                if (isset($check_timeB) && !empty($check_timeB)) {
                    $model = $model->where(" check_time >= '$check_timeB' AND check_time <=" . $check_timeD);
                }
                if (isset($extract_timeB) && !empty($extract_timeB)) {
                    $model = $model->where('extract_time', $extract_timeB, '>=')->where('extract_time', $extract_timeD, '<=');
                }

                if (isset($status) && $status == 3) {
                    $list = $model->where('(status=3 or status=5)')->all();  //1 是可以使用的cookie  2 cookies 失效

                } else {
                    $list = $model->all(['status' => $status]);  //1 是可以使用的cookie  2 cookies 失效
                }
                $result = $model->lastQueryResult();
                // 总条数
                $total = $result->getTotalCount();
                $return_data = [
                    "code" => 0,
                    "msg" => '',
                    'count' => $total,
                    'data' => $list
                ];
                $this->response()->write(json_encode($return_data));
                return true;


            }

            if ($action == "ToLead") {  #导入

                $file = $this->request()->getUploadedFile("file");
                if (!file_exists("./xls")) {
                    mkdir("./xls", 0777);
                }

                if (!file_put_contents("./xls/one.xlsx", file_get_contents($file->getTempName()))) {
                    $this->writeJson(-101, [], "上传失败");
                    return false;
                }
                $excel = new \Vtiful\Kernel\Excel(['path' => './xls']);
                $data = $excel->openFile('one.xlsx')->openSheet()->getSheetData();


                $task = \EasySwoole\EasySwoole\Task\TaskManager::getInstance();

                $task->async(new SetWhatsAppPhoneTask(['data' => $data]));

                $this->writeJson(200, [], "上传完毕");

            }


            if ($action == "check1") {
                $username = $this->request()->getQueryParam('username');
                $id = $this->request()->getQueryParam('id');
                $status = $this->request()->getQueryParam('status');  //2审核未使用  4 无效
                if (!isset($username)) {
                    $this->writeJson(-101, [], "缺少参数");
                    return false;
                }

                $one = WhatsAppModel::create()->get(['status' => 1, 'id' => $id]);
                if (!$one) {
                    $this->writeJson(-102, [], "可能被其他人已经审核");
                    return false;
                }

                $one = WhatsAppModel::create()->where(['id' => $id])->update(['username' => $username, 'status' => $status, 'check_time' => time()]);
                if (!$one) {
                    $this->writeJson(-102, [], "可能被其他人已经审核");
                    return false;
                }

                $this->writeJson(200, [], "执行成功");
                return false;

            }


            if ($action == "get2") {
                $username = $this->request()->getQueryParam('username');


//                $one = WhatsAppModel::create()->group('nickname')->where()->count();
                $one = WhatsAppModel::create()->field('count(*) as count, `nickname`')->group('nickname')->all(['username' => $username, 'status' => 3]);


                $this->writeJson(200, $one, "获取成功");
                return false;
            }


            if ($action == "clear") {
                $username = $this->request()->getQueryParam('username');
                WhatsAppModel::create()->where(['status' => 3, 'username' => $username])->update(['status' => 5]);
                $this->writeJson(200, [], "执行成功");
                return false;

            }

        } catch (\Throwable $e) {
            $this->writeJson(-1, [], $e->getMessage());
            return false;
        }

    }

}