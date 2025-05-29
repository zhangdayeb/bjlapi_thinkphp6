<?php
namespace app\controller;

use app\BaseController;
use app\service\WorkerOpenPaiService;
use think\Exception;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
       return View::fetch();
    }

    public function test()
    {

        $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 1);
// 推送的数据，包含uid字段，表示是给这个uid推送
        $data = array('code'=>'202','user_id'=>123456, 'data'=>[]);
// 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
        fwrite($client, json_encode($data)."\n");
// 读取推送结果
        echo fread($client, 8192);
    }
}
