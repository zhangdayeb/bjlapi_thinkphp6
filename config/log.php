<?php

// +----------------------------------------------------------------------
// | 日志设置
// +----------------------------------------------------------------------
return [
    // 默认日志记录通道
    'default'      => env('log.channel', 'file'),
    
    // 日志记录级别 - 关键配置！
    'level'        => [
        // 生产环境只记录重要日志
        env('APP_DEBUG') ? ['error', 'warning', 'info', 'debug'] : ['error', 'warning'],
    ],
    
    // 日志类型记录的通道
    'type_channel' => [],
    
    // 关闭全局日志写入 - 可以完全关闭日志
    'close'        => env('log.close', false),
    
    // 全局日志处理
    'processor'    => null,

    // 日志通道列表
    'channels'     => [
        'file' => [
            'type'           => 'File',
            'path'           => '',
            'single'         => false,
            'apart_level'    => [], // 独立日志级别
            'max_files'      => env('log.max_files', 30), // 最大保留30天日志
            'json'           => false,
            'processor'      => null,
            'close'          => false,
            'format'         => '[%s][%s] %s',
            'realtime_write' => false,
        ],
        
        // 专门的调试通道
        'debug' => [
            'type'           => 'File',
            'path'           => runtime_path() . 'log' . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR,
            'single'         => false,
            'apart_level'    => ['debug'],
            'max_files'      => 7, // 调试日志只保留7天
            'json'           => false,
            'close'          => !env('APP_DEBUG'), // 非调试模式关闭
            'format'         => '[%s][%s] %s',
            'realtime_write' => false,
        ],
        
        // 业务日志通道
        'business' => [
            'type'           => 'File',
            'path'           => runtime_path() . 'log' . DIRECTORY_SEPARATOR . 'business' . DIRECTORY_SEPARATOR,
            'single'         => false,
            'apart_level'    => ['info'],
            'max_files'      => 90, // 业务日志保留90天
            'json'           => false,
            'close'          => false,
            'format'         => '[%s][%s] %s',
            'realtime_write' => false,
        ],
    ],
];