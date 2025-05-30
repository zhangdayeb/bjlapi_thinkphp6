<?php
/**
 * 首页控制器
 * 处理主页面显示和WebSocket通信测试
 */
namespace app\controller;

use app\controller\common\LogHelper;
use app\BaseController;
use think\facade\Log;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        // 最简单的测试
        \think\facade\Log::info('简单测试日志');
        
        // 检查配置
        $config = config('log');
        var_dump($config);
        
        // 手动创建日志
        $logFile = runtime_path() . 'log' . DIRECTORY_SEPARATOR . 'manual_test.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " 手动测试\n", FILE_APPEND);
        
        echo "日志文件位置: " . $logFile;

        // 测试多种日志记录方式
        echo "开始测试日志记录..." . PHP_EOL;
        
        // 1. 直接使用 ThinkPHP 日志
        echo "1. 测试直接日志记录" . PHP_EOL;
        Log::info('直接使用Log::info记录');
        Log::debug('直接使用Log::debug记录');
        Log::error('直接使用Log::error记录');
        
        // 2. 使用 LogHelper
        echo "2. 测试LogHelper" . PHP_EOL;
        LogHelper::debug('LogHelper调试信息', ['test' => 'data']);
        LogHelper::info('LogHelper信息日志');
        LogHelper::error('LogHelper错误日志');
        LogHelper::warning('LogHelper警告日志');
        LogHelper::business('LogHelper业务日志');
        
        // 3. 测试指定通道
        echo "3. 测试指定通道" . PHP_EOL;
        Log::channel('debug')->debug('指定debug通道记录');
        Log::channel('business')->info('指定business通道记录');
        
        // 4. 检查环境变量
        echo "4. 检查环境变量" . PHP_EOL;
        echo "APP_DEBUG: " . var_export(env('APP_DEBUG'), true) . PHP_EOL;
        echo "DEBUG_LOG: " . var_export(env('DEBUG_LOG'), true) . PHP_EOL;
        echo "LOG_LEVEL: " . env('LOG_LEVEL') . PHP_EOL;
        echo "LOG_CLOSE: " . var_export(env('LOG_CLOSE'), true) . PHP_EOL;
        
        // 5. 检查日志配置
        echo "5. 检查日志配置" . PHP_EOL;
        $logConfig = config('log');
        echo "日志配置: " . json_encode($logConfig, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        echo "测试完成，请检查日志文件" . PHP_EOL;
        
        return View::fetch();
    }
    
    // 新增测试方法
    public function testLog()
    {
        // 强制写入测试
        $logPath = runtime_path() . 'log';
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
            echo "创建日志目录: " . $logPath . PHP_EOL;
        }
        
        $debugPath = $logPath . DIRECTORY_SEPARATOR . 'debug';
        if (!is_dir($debugPath)) {
            mkdir($debugPath, 0755, true);
            echo "创建调试日志目录: " . $debugPath . PHP_EOL;
        }
        
        // 直接写入文件测试
        $testFile = $debugPath . DIRECTORY_SEPARATOR . 'test_' . date('Y_m_d') . '.log';
        file_put_contents($testFile, date('Y-m-d H:i:s') . " 手动测试日志写入\n", FILE_APPEND);
        echo "手动写入测试文件: " . $testFile . PHP_EOL;
        
        // 测试各种日志级别
        Log::debug('测试debug级别日志');
        Log::info('测试info级别日志');
        Log::warning('测试warning级别日志');
        Log::error('测试error级别日志');
        
        // 检查是否有权限问题
        echo "日志目录权限: " . substr(sprintf('%o', fileperms($logPath)), -4) . PHP_EOL;
        echo "调试目录权限: " . substr(sprintf('%o', fileperms($debugPath)), -4) . PHP_EOL;
        
        return "日志测试完成，请查看输出信息";
    }
}
