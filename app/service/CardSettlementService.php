<?php

namespace app\service;
use app\controller\common\LogHelper;
use app\model\GameRecords;
use app\model\GameRecordsTemporary;
use app\model\Luzhu;
use app\model\LuzhuHeguan;
use app\model\LuzhuPreset;
use app\model\UserModel;
use app\job\BetMoneyLogInsert;
use app\job\UserSettleTaskJob;
use think\db\exception\DbException;
use think\facade\Db;
use think\facade\Queue;

/**
 * ========================================
 * 卡牌游戏结算服务类
 * ========================================
 * 
 * 功能说明：
 * - 处理游戏开牌后的完整结算流程
 * - 管理用户投注记录和资金变动
 * - 计算游戏胜负和赔付金额
 * - 处理洗码费和代理分成
 * - 维护露珠历史记录
 * 
 * 结算流程：
 * 1. 保存开牌结果到露珠表
 * 2. 缓存开牌信息到Redis
 * 3. 异步队列处理用户结算
 * 4. 计算用户输赢并更新余额
 * 5. 记录资金流水日志
 * 
 * @package app\service
 * @author  系统开发团队
 */
class CardSettlementService extends CardServiceBase
{
    /**
     * ========================================
     * 游戏开牌主流程
     * ========================================
     * 
     * 处理荷官开牌后的完整业务流程，包括数据保存、缓存设置、
     * 异步结算任务分发等核心功能
     * 
     * 主要步骤：
     * 1. 保存露珠数据（系统露珠 + 荷官露珠）
     * 2. 设置Redis缓存供实时推送使用
     * 3. 处理预设数据状态更新
     * 4. 启动异步用户结算任务
     * 5. 记录开牌历史信息
     * 
     * @param array $post 开牌数据（系统处理后）
     * @param array $HeguanLuzhu 荷官原始数据
     * @param int $id 预设数据ID（0表示非预设开牌）
     * @return string JSON响应字符串
     */
    public function open_game($post, $HeguanLuzhu, $id): string
    {
        LogHelper::debug('=== 开牌服务开始 ===', [
            'table_id' => $post['table_id'],
            'game_type' => $post['game_type'],
            'xue_number' => $post['xue_number'],
            'pu_number' => $post['pu_number']
        ]);
        
        LogHelper::debug('开牌数据详情', [
            'system_data' => $post,
            'heguan_data' => $HeguanLuzhu,
            'preset_id' => $id
        ]);

        // ========================================
        // 1. 数据库事务处理 - 保存露珠记录
        // ========================================
        $luzhuModel = new Luzhu();
        $save = false;
        
        LogHelper::debug('开始数据库事务');
        // 开启数据库事务
        Db::startTrans();
        try {
            // 保存系统露珠数据（可能包含预设结果）
            $luzhuModel->save($post);
            
            // 保存荷官原始露珠数据（真实开牌结果）
            LuzhuHeguan::insert($HeguanLuzhu);
            
            $save = true;
            Db::commit();

        } catch (\Exception $e) {
            $save = false;
            Db::rollback(); // 这里应该是rollback而不是commit
        }

        // ========================================
        // 2. Redis缓存设置 - 供实时推送使用
        // ========================================
        // 将开牌信息缓存到Redis，存储5秒供WebSocket推送
        $redis_key = 'table_id_' . $post['table_id'] . '_' . $post['game_type'];
        redis()->set($redis_key, $post['result_pai'], 5);

        LogHelper::debug('Redis开牌缓存设置成功', [
            'redis_key' => $redis_key,
            'ttl' => 5
        ]);

        // ========================================
        // 3. 错误处理和状态检查
        // ========================================
        if (!$save) {
            show([], 0, '开牌失败');
        }

        // 如果是预设开牌，更新预设状态为已使用
        if ($id > 0) {
            LuzhuPreset::IsStatus($id);
        }

        // ========================================
        // 4. 开牌历史记录保存
        // ========================================
        // 调用父类方法保存开牌详细信息
        $this->get_open_pai_info($post['result_pai'], $luzhuModel->id);
        LogHelper::debug('开牌历史记录保存完成');

        // ========================================
        // 5. 异步用户结算任务分发
        // ========================================
        // 添加露珠ID到结算数据中
        $post['luzhu_id'] = $luzhuModel->id;

        LogHelper::debug('开始分发结算任务', [
            'luzhu_id' => $luzhuModel->id,
            'delay' => 1
        ]);
        
        // 延迟1秒执行用户结算任务（避免数据冲突）
        $queue = Queue::later(1, UserSettleTaskJob::class, $post, 'bjl_open_queue');
        if ($queue == false) {
            LogHelper::error('结算任务分发失败');
            show([], 0, 'dismiss job queue went wrong');
        }

        LogHelper::debug('结算任务分发成功', ['queue_name' => 'bjl_open_queue']);
        LogHelper::debug('=== 开牌服务完成 ===');

        return show([]);
    }

    /**
     * ========================================
     * 用户结算核心逻辑
     * ========================================
     * 
     * 处理指定局次的所有用户投注结算，包括输赢计算、
     * 资金变动、洗码费处理等完整的结算流程
     * 
     * 结算逻辑：
     * 1. 查询本局所有投注记录
     * 2. 计算每笔投注的输赢结果
     * 3. 处理特殊赔率（幸运6、免佣庄等）
     * 4. 更新用户账户余额
     * 5. 记录资金流水日志
     * 6. 缓存派彩结果供客户端显示
     * 
     * @param int $luzhu_id 露珠记录ID
     * @param array $post 开牌数据
     * @return bool 结算是否成功
     */
    public function user_settlement($luzhu_id, $post): bool
    {
        $startTime = microtime(true);
        
        // ========================================
        // 1. 查询本局投注记录
        // ========================================
        $oddsModel = new GameRecords();

        LogHelper::debug('=== 用户结算开始 ===', [
            'luzhu_id' => $luzhu_id,
            'table_id' => $post['table_id'],
            'xue_number' => $post['xue_number'],
            'pu_number' => $post['pu_number']
        ]);
        
        LogHelper::debug('开始查询投注记录');

        // 查询条件：最近1小时内，指定台桌、靴号、铺号的未结算投注
        $betRecords = $oddsModel
            ->whereTime('created_at', date("Y-m-d H:i:s", strtotime("-1 hour")))
            ->where([
                'table_id'     => $post['table_id'],
                'game_type'    => $post['game_type'],
                'xue_number'   => $post['xue_number'],
                'pu_number'    => $post['pu_number'],
                'close_status' => 1, // 1=未结算，2=已结算
            ])
            ->select()
            ->toArray();

        LogHelper::debug('投注记录查询完成', [
            'record_count' => count($betRecords),
            'sql' => $oddsModel->getLastSql()
        ]);

        // 如果没有投注记录，直接返回成功
        if (empty($betRecords)) {
            LogHelper::debug('无投注记录，结算完成');
            return true;
        }

        LogHelper::debug('投注记录详情', $betRecords);

        // ========================================
        // 2. 初始化结算数据容器
        // ========================================
        $dataSaveRecords = [];  // 保存更新后的投注记录数据
        $userSaveDataTemp = []; // 保存用户资金变动临时数据

        // ========================================
        // 3. 计算开牌结果
        // ========================================
        LogHelper::debug('开始计算开牌结果');

        $card = new OpenPaiCalculationService();
        $pai_result = $card->runs(json_decode($post['result_pai'], true));

        LogHelper::debug('开牌计算完成', [
            'win_array' => $pai_result['win_array'],
            'zhuang_point' => $pai_result['zhuang_point'],
            'xian_point' => $pai_result['xian_point']
        ]);
        LogHelper::debug('开牌计算详细结果', $pai_result);

        LogHelper::debug('开始逐笔投注结算');
        // ========================================
        // 4. 遍历投注记录进行结算计算
        // ========================================
        foreach ($betRecords as $key => $value) {
            // 判断用户是否中奖
            $user_is_win_or_not = $card->user_win_or_not(
                intval($value['result']), 
                $pai_result
            );

            LogHelper::debug('投注结算分析', [
                'record_id' => $value['id'],
                'user_id' => $value['user_id'],
                'bet_type' => $value['result'],
                'bet_type_name' => $card->user_pai_chinese($value['result']),
                'bet_amount' => $value['bet_amt'],
                'odds' => $value['game_peilv'],
                'is_win' => $user_is_win_or_not
            ]);

            // ========================================
            // 4.1 基础结算信息设置
            // ========================================
            $dataSaveRecords[$key] = [
                // 详细描述：原详情 + 购买内容 + 开牌结果
                'detail' => $value['detail']
                    . '-购买：' . $card->user_pai_chinese($value['result'])
                    . ',开：' . $card->pai_chinese($pai_result)
                    . '|本次结果记录' . json_encode($pai_result),
                'close_status' => 2,                    // 2=已结算
                'user_id'      => $value['user_id'],    // 用户ID
                'win_amt'      => 0,                    // 输赢金额默认0
                'id'           => $value['id'],         // 投注记录ID
                'lu_zhu_id'    => $luzhu_id,           // 关联露珠ID
                'table_id'     => $value['table_id'],   // 台桌ID
                'game_type'    => $value['game_type'],  // 游戏类型
            ];

            // ========================================
            // 4.2 特殊赔率预处理
            // ========================================
            $tempPelv = $value['game_peilv']; // 默认赔率

            // 用户投注幸运 6
            if ($value['result'] == 3) {
                // 幸运6特殊处理：根据庄家牌数选择赔率
                $pei_lv = explode('/', $value['game_peilv']); // 格式：12/20
                if ($pai_result['luckySize'] == 2) {
                    $tempPelv = intval($pei_lv[0]); // 2张牌赔率
                } elseif ($pai_result['luckySize'] == 3) {
                    $tempPelv = intval($pei_lv[1]); // 3张牌赔率
                }
            }
            // 用户投注庄 当局结果幸运6 并且开了免佣
            if ($value['result'] == 8) {
                // 免佣庄特殊处理：庄6点赢只赔50%
                if ($value['is_exempt'] == 1 && $pai_result['zhuang_point'] == 6) {
                    $tempPelv = 0.5;
                }
            }

            $dataSaveRecords[$key]['game_peilv'] = $tempPelv;
            $moneyWinTemp = $tempPelv * $value['bet_amt']; // 中奖金额 = 赔率 × 本金

            // ========================================
            // 4.3 输赢结算处理
            // ========================================
            if ($user_is_win_or_not) {
                // --- 中奖处理 ---
                $dataSaveRecords[$key]['win_amt'] = $moneyWinTemp;
                $dataSaveRecords[$key]['delta_amt'] = $moneyWinTemp + $value['bet_amt']; // 返还 = 奖金 + 本金

                // 用户资金变动记录
                $userSaveDataTemp[$key] = [
                    'money_balance_add_temp' => $dataSaveRecords[$key]['delta_amt'],
                    'id'                     => $value['user_id'],
                    'win'                    => $moneyWinTemp,
                    'bet_amt'                => $value['bet_amt'],
                ];
            } else {
                // --- 未中奖处理 ---
                // if ($pai_result['win'] == 3) {
                // 和局特殊处理 庄闲幸运6 退回 大小老虎 龙7熊8 没有退回
                if (in_array(3,$pai_result['win_array'])) {
                    // 和局特殊处理：庄闲投注退回本金
                    if ($value['result'] == 8 || $value['result'] == 6) {
                        $userSaveDataTemp[$key] = [
                            'money_balance_add_temp' => $value['bet_amt'], // 退回本金
                            'id'                     => $value['user_id'],
                            'win'                    => 0,
                            'bet_amt'                => $value['bet_amt'],
                        ];

                        $dataSaveRecords[$key]['win_amt'] = 0;
                        $dataSaveRecords[$key]['delta_amt'] = 0;
                        $dataSaveRecords[$key]['agent_status'] = 1;
                        $dataSaveRecords[$key]['shuffling_amt'] = 0;
                        $dataSaveRecords[$key]['shuffling_num'] = 0;
                    } else {
                        // 其他投注类型输掉本金
                        $dataSaveRecords[$key]['win_amt'] = $value['bet_amt'] * -1;
                    }
                } else {
                    // 正常输牌：输掉本金
                    $dataSaveRecords[$key]['win_amt'] = $value['bet_amt'] * -1;
                }
            }
        }

        // ========================================
        // 5. 合并同用户的多笔投注
        // ========================================
        $user_save_data = [];
        if (!empty($userSaveDataTemp)) {
            foreach ($userSaveDataTemp as $v) {
                if (array_key_exists($v['id'], $user_save_data)) {
                    // 同用户多笔投注金额累加
                    $user_save_data[$v['id']]['money_balance_add_temp'] += $v['money_balance_add_temp'];
                } else {
                    $user_save_data[$v['id']] = $v;
                }
            }
        }

        // ========================================
        // 6. 生成派彩显示数据
        // ========================================
        if (!empty($dataSaveRecords)) {
            $userCount = [];
            
            // 按用户汇总输赢金额
            foreach ($dataSaveRecords as $v) {
                if (array_key_exists($v['user_id'], $userCount)) {
                    $userCount[$v['user_id']]['win_amt'] += $v['win_amt'];
                } else {
                    $userCount[$v['user_id']] = $v;
                }
            }

            // 将派彩结果存入Redis，供客户端显示（存储5秒）
            foreach ($userCount as $v) {
                $redis_key = 'user_' . $v['user_id'] . '_table_id_' . $v['table_id'] . '_' . $v['game_type'];
                redis()->set($redis_key, $v['win_amt'], 5);
            }
        }

        // ========================================
        // 7. 数据库事务处理 - 更新用户余额和投注记录
        // ========================================
        LogHelper::debug('开始用户余额更新事务');

        $UserModel = new UserModel();
        $UserModel->startTrans();
        
        try {
            // 更新用户余额
            if (!empty($userSaveDataTemp)) {
                foreach ($userSaveDataTemp as $key => $value) {
                    // 获取用户当前余额（加锁防止并发）
                    $find = $UserModel->where('id', $value['id'])->lock(true)->find();

                    // 准备资金流水记录
                    $save = [
                        'money_before' => $find->money_balance,
                        'money_end'    => $find->money_balance + $value['money_balance_add_temp'],
                        'uid'          => $value['id'],
                        'type'         => 1,
                        'status'       => 503, // 百家乐结算
                        'source_id'    => $luzhu_id,
                        'money'        => $value['money_balance_add_temp'],
                        'create_time'  => date('Y-m-d H:i:s'),
                        'mark'         => '下注结算--变化:' . $value['money_balance_add_temp'] 
                                        . '下注：' . $value['bet_amt'] 
                                        . '总赢：' . $value['win']
                    ];

                    // 更新用户余额
                    $user_update = $UserModel->where('id', $value['id'])
                        ->inc('money_balance', $value['money_balance_add_temp'])
                        ->update();

                    // 如果余额更新成功，将资金记录推入Redis队列
                    if ($user_update) {
                        redis()->LPUSH('bet_settlement_money_log', json_encode($save));
                    }
                }
            }

            // 批量更新投注记录状态
            if (!empty($dataSaveRecords)) {
                $oddsModel->saveAll($dataSaveRecords);
            }

            $UserModel->commit();
        } catch (DbException $e) {
            $UserModel->rollback();
            return false;
        }

        // ========================================
        // 8. 后续处理任务
        // ========================================
        LogHelper::debug('开始后续处理任务');
        // 延迟2秒执行资金日志写入任务
        Queue::later(2, BetMoneyLogInsert::class, $post, 'bjl_money_log_queue');
        LogHelper::debug('资金日志写入任务已加入队列');

        // 清理临时投注记录
        GameRecordsTemporary::destroy(function($query) use ($post) {
            $query->where('table_id', $post['table_id']);
        });
        LogHelper::debug('临时投注记录清理完成');

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        LogHelper::debug('=== 用户结算完成 ===', [
            'luzhu_id' => $luzhu_id,
            'duration_ms' => $duration,
            'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2)
        ]);

        return true;
    }
}

/**
 * ========================================
 * 类使用说明和技术要点
 * ========================================
 * 
 * 1. 结算流程控制：
 *    - 开牌 -> 数据保存 -> Redis缓存 -> 异步结算 -> 推送结果
 *    - 使用队列异步处理，避免阻塞用户操作
 * 
 * 2. 数据一致性保证：
 *    - 使用数据库事务确保数据完整性
 *    - 用户余额更新时加锁防止并发问题
 *    - Redis缓存设置合理过期时间
 * 
 * 3. 特殊规则处理：
 *    - 幸运6：根据庄家牌数选择不同赔率
 *    - 免佣庄：庄6点赢只赔50%
 *    - 和局：庄闲投注退回本金
 * 
 * 4. 性能优化：
 *    - 批量数据库操作减少IO次数
 *    - Redis队列异步处理日志写入
 *    - 合理的缓存策略提升响应速度
 * 
 * 5. 错误处理：
 *    - 完整的异常捕获和事务回滚
 *    - 队列任务失败重试机制
 *    - 数据校验和边界条件处理
 */