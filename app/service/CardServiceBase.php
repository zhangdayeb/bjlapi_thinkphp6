<?php


namespace app\service;


use think\facade\Db;

class CardServiceBase
{
    /**
     * redis 开牌展示
     * @param $table_id
     * @param $game_type
     */
    public function get_pai_info($table_id, $game_type)
    {
        $pai_data = redis()->get('table_id_' . $table_id . '_' . $game_type);
        if (empty($pai_data)) return false;
        $service = new WorkerOpenPaiService();
        switch ($game_type){
            case 3:
                return $service->get_pai_info_bjl($pai_data);
                break;
        }
        return [];
    }

    //获取派彩金额
    public function get_payout_money($user,$table_id, $game_type)
    {
        $money = redis()->get('user_'.$user.'_table_id_' . $table_id . '_' . $game_type);
        if ($money === null) return false;
        redis()->del('user_'.$user.'_table_id_' . $table_id . '_' . $game_type);
        return $money;
    }

    public function get_open_pai_info($pai_result, $id)
    {
        $pai['open_pai'] = $pai_result;
        $pai['luzhu_id'] = $id;
        Db::name('dianji_lu_zhu_open_pai')->insert($pai);
    }
}

