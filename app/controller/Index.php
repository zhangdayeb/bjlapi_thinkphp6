<?php
/**
 * 首页控制器
 * 处理主页面显示和WebSocket通信测试
 */
namespace app\controller;

use app\controller\common\LogHelper;
use app\BaseController;
use app\service\WorkerOpenPaiService;
use think\Exception;
use think\facade\View;

/**
 * 首页控制器类
 * 继承自BaseController，提供基础的页面展示和Socket通信功能
 */
class Index extends BaseController
{
    /**
     * 首页方法
     * 显示开牌数据详情页面
     * 
     * @return string 返回渲染后的视图
     */
    public function index()
    {
        // 记录调试日志，标记进入开牌数据详情页面
        LogHelper::debug('开牌数据详情', ['abc','cdf']);
        
        // 返回对应的视图模板
        return View::fetch();
    }

    /**
     * 测试方法
     * 用于测试与WebSocket服务器的通信连接
     * 通过TCP Socket向本地5678端口发送数据并获取响应
     * 
     * @return void 直接输出响应结果
     */
    public function test()
    {
        // 创建TCP客户端连接到本地5678端口
        // 参数说明：
        // - tcp://127.0.0.1:5678: 目标服务器地址和端口
        // - $errno: 错误代码（引用传递）
        // - $errmsg: 错误信息（引用传递）  
        // - 1: 连接超时时间（秒）
        $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 1);
        
        // 构造推送数据
        // 包含uid字段，表示向指定用户推送消息
        $data = array(
            'code'    => '202',           // 响应状态码
            'user_id' => 123456,          // 目标用户ID
            'data'    => []               // 推送的具体数据内容
        );
        
        // 发送JSON格式数据到服务器
        // 注意：5678端口使用Text协议，需要在数据末尾添加换行符(\n)
        fwrite($client, json_encode($data) . "\n");
        
        // 读取服务器响应结果并直接输出
        // 最多读取8192字节的数据
        echo fread($client, 8192);
        
        // 注意：这里应该添加socket连接的关闭和错误处理
        // fclose($client);
    }
}