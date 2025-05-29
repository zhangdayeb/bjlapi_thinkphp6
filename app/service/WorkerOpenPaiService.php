<?php


namespace app\service;


use app\model\Table;
use app\model\GameRecords;

class WorkerOpenPaiService
{
    //百家乐开牌
    public function get_pai_info_bjl($pai_data)
    {
        $pai_data = $pai_info = json_decode($pai_data, true);
        //h r m f
        $info = [];
        foreach ($pai_info as $key => $value) {
            if ($value == '0|0') {
                unset($pai_info[$key]);
                continue;
            }
            $pai = explode('|', $value);
            if ($key == 1 || $key == 2 || $key == 3) {
                $info['zhuang'][$key] = $pai[1] . $pai[0] . '.png';
            } else {
                $info['xian'][$key] = $pai[1] . $pai[0] . '.png';
            }
        };
        //获取扑克点数
        $card = new OpenPaiCalculationService();
        $pai_result = $card->runs($pai_data);
        $pai_flash = $card->pai_flash($pai_result);
        return ['result' => $pai_result, 'info' => $info, 'pai_flash' => $pai_flash];
    }

    //获取台桌信息
    public function get_table_info($id,$user_id)
    {
        $id = intval($id);
        $user_id = intval($user_id);
        if ($id <= 0) return [];
        if ($user_id <= 0) return [];

        //获取台桌信息
        $info = Table::page_one($id);
        //获取台桌倒计时和视频地址
        $info = Table::table_opening_count_down($info);
        //获取最新的靴号和铺号
        $bureau_number = bureau_number($id, true);
        $info['bureau_number'] = $bureau_number['bureau_number'];
        //获取当前用户是否下注，下注了记住免佣状态。免佣状态
        $user['id']= $user_id;
        $info['is_exempt'] = GameRecords::user_status_bureau_number_is_exempt($id, $bureau_number['xue'], $user);
        return $info;
    }
    //    // 台座露珠列表
//    public function lu_zhu_list($table_id = 1, $game_type = 3)
//    {
//        //百家乐台桌
//        $info = Luzhu::table_lu_zhu_list($table_id, $game_type);
//        return $info;
//    }
//    //获取台桌列表
//    public function get_table_list($game_type = 3)
//    {
//        //每个游戏的台桌列表。不存在就是所有台桌
//        $map = [];
//        if ($game_type > 0) $map[] = ['game_type','=',$game_type];
//        //if ($table_id > 0) $map[] = ['id','=',$table_id];
//        //$map['status'] = 1;
//
//        $list = Table::page_repeat($map, 'list_order asc');
//        $list = $list->hidden(['game_play_staus', 'is_dianji', 'is_weitou', 'is_diantou', 'list_order']);
//        //计算台桌倒计时
//        if (empty($list)) return $list;
//        foreach ($list as $key => &$value) {
//            //获取视频地址
//            $value = Table::table_opening_count_down($value);
//            $value->p = rand(10, 40) . '.' . rand(1, 9) . 'k/' . rand(10, 40);
//            $value->t = rand(10, 40) . '.' . rand(1, 9) . 'k/' . rand(10, 40);
//            $value->b = rand(10, 40) . '.' . rand(1, 9) . 'k/' . rand(10, 40);
//        }
//        return $list;
//    }
//
//
//    public function lu_zhu_and_table_info(array $data)
//    {   //获取露珠信息
//        if ($data['game_table_type'] == 'luzhu_list'){
//            return $this->lu_zhu_list($data['table_id']);
//        }
//        //获取台座列表
//        if ($data['game_table_type'] == 'table_list'){
//            return $this->get_table_list($data['game_type']);
//        }
//        return [];
//    }
}