<?php
namespace app\controller\common;
use think\facade\Log;

class LogHelper 
{
    // 调试日志 - 只在开发环境记录
    public static function debug($message, $data = [])
    {
        if (env('APP_DEBUG') || env('DEBUG_LOG')) {
            Log::channel('debug')->debug($message . (!empty($data) ? ' 数据：' . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
        }
    }
    
    // 业务日志 - 重要业务流程
    public static function business($message, $data = [])
    {
        Log::channel('business')->info($message . (!empty($data) ? ' 数据：' . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
    }
    
    // 错误日志 - 始终记录
    public static function error($message, $exception = null)
    {
        $errorMsg = $message;
        if ($exception instanceof \Exception) {
            $errorMsg .= ' 错误：' . $exception->getMessage();
            if (env('APP_DEBUG')) {
                $errorMsg .= ' 堆栈：' . $exception->getTraceAsString();
            }
        }
        Log::error($errorMsg);
    }
    
    // 警告日志 - 始终记录
    public static function warning($message, $data = [])
    {
        Log::warning($message . (!empty($data) ? ' 数据：' . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
    }
    
    // 条件信息日志 - 可控制
    public static function info($message, $data = [], $force = false)
    {
        // $force = true 时强制记录，否则根据环境决定
        if ($force || env('APP_DEBUG') || in_array('info', explode(',', env('LOG_LEVEL', 'error,warning')))) {
            Log::info($message . (!empty($data) ? ' 数据：' . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
        }
    }
}