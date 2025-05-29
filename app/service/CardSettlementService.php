<?php


namespace app\service;


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

class CardSettlementService extends CardServiceBase
{
    /**
     * 牌面结算
     * 1 计算完成 修改用户总赢。修改用户金额
     * 2 游戏结果计算
     * 3 洗码费计算(如果出现台面作废，洗码费就不写入，所有必须是正常开牌的才把经费通过定时任务发放)
     * 4 代理计算
     * 5 开牌以后 开牌以后的下注信息 转移 插入到 资金记录表 redis  TransferBetService 执行
     * @param $post
     */
    public function open_game($post,$HeguanLuzhu,$id): string
    {
        $luzhuModel = new Luzhu();
        //插入开牌信息
        $save = false;
        Db::startTrans();
        try {
            $luzhuModel->save($post);
            LuzhuHeguan::insert($HeguanLuzhu);
            $save = true;
            Db::commit();
        }catch (\Exception $e){
            $save = false;
            Db::commit();
        }

        //开牌的时候 把开牌信息存入到redis...用作桌面 牌显示
        redis()->set('table_id_' . $post['table_id'] . '_' . $post['game_type'], $post['result_pai'], 5);

        if (!$save) show([], 0, '开牌失败');
        if ($id >0 ) LuzhuPreset::IsStatus($id);
        //开牌信息存入数据库
        $this->get_open_pai_info($post['result_pai'], $luzhuModel->id);
        $post['luzhu_id'] = $luzhuModel->id;
        $queue = Queue::later(1, UserSettleTaskJob::class, $post,'bjl_open_queue');
        if ($queue == false) {
            show([], 0, 'dismiss job queue went wrong');
        }

        return show([]);
        //return $this->user_settlement($luzhuModel->id, $post);
    }

    //用户结算以及写入订单信息,单独写方便后面检查
    public function user_settlement($luzhu_id, $post): bool
    {
        $oddsModel = new GameRecords(); // 获取 GameRecords 记录
        //1 触发该牌桌 所有购买的用户 是否中奖。中奖后修改当前下单信息
        //得到本次下注用户订单列表.只查询前一个小时的。不需要查询太久了的
        $betRecords = $oddsModel
            ->whereTime('created_at', date("Y-m-d H:i:s", strtotime("-1 hour")))
            ->where([
                'table_id' => $post['table_id'],
                'game_type' => $post['game_type'],
                'xue_number' => $post['xue_number'],
                'pu_number' => $post['pu_number'],
                'close_status' => 1,
            ])
            ->select()
            ->toArray();
        //对本次下单用户计算获得金额
        if (empty($betRecords)) return true; // 为空，则直接返回
        // 有数据的情况
        $dataSaveRecords = [];                          //保存当前修改下注信息数据
        $userSaveDataTemp = [];                         //保存修改用户获取金额

        $card = new OpenPaiCalculationService();        //获得本次开牌结果
        $pai_result = $card->runs(json_decode($post['result_pai'], true));  // 通过开牌结果
        // 遍历未结算的投注记录
        foreach ($betRecords as $key => $value) {
            $user_is_win_or_not = $card->user_win_or_not(intval($value['result']), $pai_result);//查询当前用户是否中奖   返回 false 是没中奖  true是中奖
            $dataSaveRecords[$key]['detail'] = $value['detail']
                . '-购买：'
                . $card->user_pai_chinese($value['result'])
                . ',开：'
                . $card->pai_chinese($pai_result)
                . '|本次结果记录'
                . json_encode($pai_result);
            $dataSaveRecords[$key]['close_status'] = 2;             //2已结算
            $dataSaveRecords[$key]['user_id'] = $value['user_id'];  //用户id
            $dataSaveRecords[$key]['win_amt'] = 0;                  //会员总赢默认为 0
            $dataSaveRecords[$key]['id'] = $value['id'];            // 为了更新的
            $dataSaveRecords[$key]['lu_zhu_id'] = $luzhu_id;        // 记录更新对应的露珠信息
            $dataSaveRecords[$key]['table_id'] = $value['table_id'];
            $dataSaveRecords[$key]['game_type'] = $value['game_type'];

            // 赢牌处理
            $tempPelv = 0;          // 此处计算的临时赔率
            // 赔率 预处理 开始
            // 投注结果 为 “幸运6” 因为牌型不同 赔率预先 处理 //判断是否是购买的幸运 6 是幸运6时计算前面是三张牌还是 2张牌，2张牌对应赔率前面，3张对应后面
            if ($value['result'] == 3) {
                $pei_lv = explode('/', $value['game_peilv']); // 此处的赔率结果为 12/20 的形式 数据库内存放的是
                // 新增调整 幸运 6 逻辑   主要是 庄 2张牌 跟 庄3张牌，对应的 不一样
                if ($pai_result['luckySize'] == 2) {
                    $value['game_peilv'] = intval($pei_lv[0]);
                } elseif ($pai_result['luckySize'] == 3) {
                    $value['game_peilv'] = intval($pei_lv[1]);
                }
            } elseif ($value['result'] == 8) { // 投注结果 为 “庄” 因为是否免佣 进行赔率的预先处理  1/0.95
                // 此处数据库后台记录的 赔率 已经调整为 1 或者 0.95了 不用现在判断了
                if ($value['is_exempt'] == 1) {
                    // 免佣台 庄 6点赢 赔率为 一半
                    if ($pai_result['zhuang_point'] == 6) {
                        $value['game_peilv'] = 0.5; // 庄幸运 6 赔付一半
                    }
                }
            } else {// 默认情况 直接读取写入后的赔率
                $tempPelv = $value['game_peilv'];
            }
            $dataSaveRecords[$key]['game_peilv'] = $tempPelv;           // 把预先处理过的赔率 存储保存记录中
            $moneyWinTemp = $value['game_peilv'] * $value['bet_amt'];   // 中奖金额 本金 * 赔率
            //判断该用户是否 赢了。 true 赢   false 输  用户购买 结果和开奖结果是否一样
            if ($user_is_win_or_not == true) {
                // 赔率 预处理 结束
                $dataSaveRecords[$key]['win_amt'] = $moneyWinTemp;//    中奖金额
                $dataSaveRecords[$key]['delta_amt'] = $moneyWinTemp + $value['bet_amt'];// 中奖后返还金额 = 下注本金 + 本金
                // 用户临时钱数
                $userSaveDataTemp[$key]['money_balance_add_temp'] = $dataSaveRecords[$key]['delta_amt']; //用户需要增加的金额 = 下注本金 + 本金
                $userSaveDataTemp[$key]['id'] = $value['user_id'];
                $userSaveDataTemp[$key]['win'] = $moneyWinTemp;
                $userSaveDataTemp[$key]['bet_amt'] = $value['bet_amt'];
            } else {
                // 输牌处理  免佣台  | 非免佣
                //当开奖结果为 和局 ，在免佣 与 非免佣状态，如果购买了庄闲 退回
                if ($pai_result['win'] == 3) {
                    // 如果购买了庄闲 退回
                    if ($value['result'] == 8 || $value['result'] == 6) {
                        // 个人资金记录
                        $userSaveDataTemp[$key]['money_balance_add_temp'] = $value['bet_amt'];//本金退还  ******************待处理
                        $userSaveDataTemp[$key]['id'] = $value['user_id'];
                        $userSaveDataTemp[$key]['win'] = $value['win_amt'];
                        $userSaveDataTemp[$key]['bet_amt'] = $value['bet_amt'];
                        // 游戏记录
                        $dataSaveRecords[$key]['win_amt'] = 0; //会员总赢
                        $dataSaveRecords[$key]['delta_amt'] = 0;//变化金额0
                        $dataSaveRecords[$key]['agent_status'] = 1;//代理已结算
                        $dataSaveRecords[$key]['shuffling_amt'] = 0;//无洗码费
                        $dataSaveRecords[$key]['shuffling_num'] = 0;//无洗码费
                    } else {
                        // 其它的还是要杀掉
                        $dataSaveRecords[$key]['win_amt'] = $value['bet_amt'] * -1;//    中奖金额
                    }
                } else {
                    $dataSaveRecords[$key]['win_amt'] = $value['bet_amt'] * -1;//    中奖金额
                }
            }
        }

        //组装用户资金数据 累计
        $user_save_data = [];
        if (!empty($userSaveDataTemp)) {
            foreach ($userSaveDataTemp as $v) {
                //如果存在说明用户购买了多条
                if (array_key_exists($v['id'], $user_save_data)) {
                    $user_save_data[$v['id']]['money_balance_add_temp'] += $v['money_balance_add_temp'];
                } else {
                    $user_save_data[$v['id']] = $v;
                }
            }
        }

        ######统计用户总赢总输。用于派彩显示######
        if (!empty($dataSaveRecords)) {
            //组装派彩数据
            $userCount = [];
            foreach ($dataSaveRecords as $v) {

                if (array_key_exists($v['user_id'], $userCount)) {
                    $userCount[$v['user_id']]['win_amt'] += $v['win_amt'];
                } else {
                    $userCount[$v['user_id']] = $v;
                }
            }

            //存入redis
            foreach ($userCount as $v) {
                redis()->set('user_' . $v['user_id'] . '_table_id_' . $v['table_id'] . '_' . $v['game_type'], $v['win_amt'], 5);
            }
        }

        ######结束######
        $UserModel = new UserModel();

        // 启动事务
        $UserModel->startTrans();
        try {
            //写入用户余额
            if (!empty($userSaveDataTemp)) {
                foreach ($userSaveDataTemp as $key => $value) {
                    $save = array();//查询用户当前余额
                    $find = $UserModel->where('id', $value['id'])->lock(true)->find();
                    $save['money_before'] = $find->money_balance;
                    $save['money_end'] = $find->money_balance + $value['money_balance_add_temp'];
                    $save['uid'] = $value['id'];
                    $save['type'] = 1;
                    $save['status'] = 503;
                    $save['source_id'] = $luzhu_id;
                    $save['money'] = $value['money_balance_add_temp'];
                    $save['create_time'] = date('Y-m-d H:i:s');
                    $save['mark'] = '下注结算--变化:' . $value['money_balance_add_temp'] . '下注：' . $value['bet_amt'] . '总赢：' . $value['win'];
                    $user_update =  $UserModel->where('id', $value['id'])->inc('money_balance', $value['money_balance_add_temp'])->update();
                    if ($user_update){
                        redis()->LPUSH('bet_settlement_money_log',json_encode($save));
                    }
                }
            }
            !empty($dataSaveRecords) && $oddsModel->saveAll($dataSaveRecords);
            // 提交事务
            $UserModel->commit();
        }
        catch (DbException $e) {
            $UserModel->rollback();
            // 回滚事务
            return false;
        }

        //执行用户资金写入
        Queue::later(2, BetMoneyLogInsert::class, $post,'bjl_money_log_queue');
        GameRecordsTemporary::destroy(function($query)use ($post){
            $query->where('table_id',$post['table_id']);
        });
        return true;
    }
}

