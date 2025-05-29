<?php

namespace app\controller\order;

use app\business\Curl;
use app\business\RequestUrl;
use app\controller\Base;
use app\model\GameRecords;

class OrderBase extends Base
{

    //获取配置文件
    public function get_config(string $name)
    {
        $url = env('curl.http', '0.0.0.0') . RequestUrl::conf_url();
        $data = Curl::post($url, ['name' => $name]);
        if ($data['code'] != 200) return show([], 200, $data['message']);
        return $data['data'];
    }

    //用户限红
    public function user_xian_hong($table_id, $value, $xue_number, $odds, $table_info)
    {
        //查询用户本次赔率下注的金额//当前赔率本次下注 加上前面下注
        $money = $value['money'];
        //用户限红
        if (isset(self::$user['is_xian_hong']) && self::$user['is_xian_hong'] == 1){
            switch ($odds->id) {
                //百家乐限红
                case 2://百家乐 闲对
                    if ($money < self::$user['bjl_xian_hong_xian_dui_min']) show([], config('ToConfig.http_code.error'), lang('limit red leisure to bet at least') . ':' . self::$user['bjl_xian_hong_xian_dui_min']);
                    if ($money > self::$user['bjl_xian_hong_xian_dui_max']) show([], config('ToConfig.http_code.error'), lang('limit red leisure to bet the most') . ':' . self::$user['bjl_xian_hong_xian_dui_max']);
                    //台座限红
                    break;
                case 3: //百家乐 幸运6
                    if ($money < self::$user['bjl_xian_hong_lucky6_min']) show([], config('ToConfig.http_code.error'), lang('limit red lucky 6 minimum bet') . ':' . self::$user['bjl_xian_hong_lucky6_min']);
                    if ($money > self::$user['bjl_xian_hong_lucky6_max']) show([], config('ToConfig.http_code.error'), lang('limit red lucky 6 to bet most') . ':' . self::$user['bjl_xian_hong_lucky6_max']);
                    break;
                case 4://百家乐庄 对
                    if ($money < self::$user['bjl_xian_hong_zhuang_dui_min']) show([], config('ToConfig.http_code.error'), lang('limit red zhuang bet the least') . ':' . self::$user['bjl_xian_hong_zhuang_dui_min']);
                    if ($money > self::$user['bjl_xian_hong_zhuang_dui_max']) show([], config('ToConfig.http_code.error'), lang('limit red zhuang to bet most') . ':' . self::$user['bjl_xian_hong_zhuang_dui_max']);
                    break;
                case 6: //百家乐 闲
                    if ($money < self::$user['bjl_xian_hong_xian_min']) show([], config('ToConfig.http_code.error'), lang('limit red leisure and bet at least') . ':' . self::$user['bjl_xian_hong_xian_min']);
                    if ($money > self::$user['bjl_xian_hong_xian_max']) show([], config('ToConfig.http_code.error'), lang('limit red leisure and bet at most') . ':' . self::$user['bjl_xian_hong_xian_max']);
                    break;
                case 7://百家乐 和
                    if ($money < self::$user['bjl_xian_hong_he_min']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet least') . ':' . self::$user['bjl_xian_hong_he_min']);
                    if ($money > self::$user['bjl_xian_hong_he_max']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet most') . ':' . self::$user['bjl_xian_hong_he_max']);
                    break;
                case 8://百家乐 庄
                    if ($money < self::$user['bjl_xian_hong_zhuang_min']) show([], config('ToConfig.http_code.error'), lang('limit red and zhuang bet least') . ':' . self::$user['bjl_xian_hong_zhuang_min']);
                    if ($money > self::$user['bjl_xian_hong_zhuang_max']) show([], config('ToConfig.http_code.error'), lang('limit red and zhuang bet most') . ':' . self::$user['bjl_xian_hong_zhuang_max']);
                    break;
                //20开始 龙虎斗
                case 20://龙虎斗 龙
                    if ($money < self::$user['lh_xian_hong_long_min']) show([], config('ToConfig.http_code.error'), lang('limit red and loong bet least') . ':' . self::$user['lh_xian_hong_long_min']);
                    if ($money > self::$user['lh_xian_hong_long_max']) show([], config('ToConfig.http_code.error'), lang('limit red and loong bet most') . ':' . self::$user['lh_xian_hong_long_max']);
                    break;
                case 21://龙虎斗 虎
                    if ($money < self::$user['lh_xian_hong_hu_min']) show([], config('ToConfig.http_code.error'), lang('limit red and tiger bet least') . ':' . self::$user['lh_xian_hong_hu_min']);
                    if ($money > self::$user['lh_xian_hong_hu_max']) show([], config('ToConfig.http_code.error'), lang('limit red and tiger bet most') . ':' . self::$user['lh_xian_hong_hu_max']);
                    break;
                case 22://龙虎斗 和
                    if ($money < self::$user['lh_xian_hong_he_min']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet least') . ':' . self::$user['lh_xian_hong_he_min']);
                    if ($money > self::$user['lh_xian_hong_he_max']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet most') . ':' . self::$user['lh_xian_hong_he_max']);
                    break;
                case 30://牛牛翻倍 闲1
                case 32://牛牛翻倍 闲2
                case 34://牛牛翻倍 闲3
                    break;
                case 31://牛牛平倍 闲1
                case 33://牛牛平倍 闲2
                case 35://牛牛平倍 闲3
                    break;
                case 36://超级闲1
                case 37://超级闲2
                case 38://超级闲3
                    break;
                case 40://三公翻倍闲1
                case 42://三公翻倍闲2
                case 44://三公翻倍闲3
                    break;
                case 41://三公平倍闲1
                case 43://三公平倍闲2
                case 45://三公平倍闲3
                    break;
                case 46://三公超级闲1
                case 47://三公超级闲2
                case 48://三公超级闲3
                    break;
            }
            return true;
        }

        //台座限红
        if (isset($table_info['is_table_xian_hong']) && $table_info['is_table_xian_hong'] == 1) {
            switch ($odds->id) { //台座限红
                //百家乐限红
                case 2://百家乐 闲对
                    if ($money < $table_info['bjl_xian_hong_xian_dui_min']) show([], config('ToConfig.http_code.error'), lang('limit red leisure to bet at least') . ':' . $table_info['bjl_xian_hong_xian_dui_min']);
                    if ($money > $table_info['bjl_xian_hong_xian_dui_max']) show([], config('ToConfig.http_code.error'), lang('limit red leisure to bet the most') . ':' . $table_info['bjl_xian_hong_xian_dui_max']);
                    break;
                case 3: //百家乐 幸运6
                    if ($money < $table_info['bjl_xian_hong_lucky6_min']) show([], config('ToConfig.http_code.error'), lang('limit red lucky 6 minimum bet') . ':' . $table_info['bjl_xian_hong_lucky6_min']);
                    if ($money > $table_info['bjl_xian_hong_lucky6_max']) show([], config('ToConfig.http_code.error'), lang('limit red lucky 6 to bet most') . ':' . $table_info['bjl_xian_hong_lucky6_max']);
                    break;
                case 4://百家乐庄 对
                    if ($money < $table_info['bjl_xian_hong_zhuang_dui_min']) show([], config('ToConfig.http_code.error'), lang('limit red zhuang bet the least') . ':' . $table_info['bjl_xian_hong_zhuang_dui_min']);
                    if ($money > $table_info['bjl_xian_hong_zhuang_dui_max']) show([], config('ToConfig.http_code.error'), lang('limit red zhuang to bet most') . ':' . $table_info['bjl_xian_hong_zhuang_dui_max']);

                    break;
                case 6: //百家乐 闲
                    if ($money < $table_info['bjl_xian_hong_xian_min']) show([], config('ToConfig.http_code.error'), lang('limit red leisure and bet at least') . ':' . $table_info['bjl_xian_hong_xian_min']);
                    if ($money > $table_info['bjl_xian_hong_xian_max']) show([], config('ToConfig.http_code.error'), lang('limit red leisure and bet at most') . ':' . $table_info['bjl_xian_hong_xian_max']);
                    break;
                case 7://百家乐 和
                    if ($money < $table_info['bjl_xian_hong_he_min']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet least') . ':' . $table_info['bjl_xian_hong_he_min']);
                    if ($money > $table_info['bjl_xian_hong_he_max']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet most') . ':' . $table_info['bjl_xian_hong_he_max']);
                    break;
                case 8://百家乐 庄
                    if ($money < $table_info['bjl_xian_hong_zhuang_min']) show([], config('ToConfig.http_code.error'), lang('limit red and zhuang bet least') . ':' . $table_info['bjl_xian_hong_zhuang_min']);
                    if ($money > $table_info['bjl_xian_hong_zhuang_max']) show([], config('ToConfig.http_code.error'), lang('limit red and zhuang bet most') . ':' . $table_info['bjl_xian_hong_zhuang_max']);
                    break;
                //20开始 龙虎斗
                case 20://龙虎斗 龙
                    if ($money < $table_info['lh_xian_hong_long_min']) show([], config('ToConfig.http_code.error'), lang('limit red and loong bet least') . ':' . $table_info['lh_xian_hong_long_min']);
                    if ($money > $table_info['lh_xian_hong_long_max']) show([], config('ToConfig.http_code.error'), lang('limit red and loong bet most') . ':' . $table_info['lh_xian_hong_long_max']);
                    break;
                case 21://龙虎斗 虎
                    if ($money < $table_info['lh_xian_hong_hu_min']) show([], config('ToConfig.http_code.error'), lang('limit red and tiger bet least') . ':' . $table_info['lh_xian_hong_hu_min']);
                    if ($money > $table_info['lh_xian_hong_hu_max']) show([], config('ToConfig.http_code.error'), lang('limit red and tiger bet most') . ':' . $table_info['lh_xian_hong_hu_max']);
                    break;
                case 22://龙虎斗 和
                    if ($money < $table_info['lh_xian_hong_he_min']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet least') . ':' . $table_info['lh_xian_hong_he_min']);
                    if ($money > $table_info['lh_xian_hong_he_max']) show([], config('ToConfig.http_code.error'), lang('limit red and minimum bet most') . ':' . $table_info['lh_xian_hong_he_max']);
                    break;
                case 30://牛牛翻倍 闲1
                case 32://牛牛翻倍 闲2
                case 34://牛牛翻倍 闲3
                    if ($money < $table_info['nn_xh_fanbei_min']) show([], config('ToConfig.http_code.error'), lang('limit red minimum least') . ':' . $table_info['nn_xh_fanbei_min']);
                    if ($money > $table_info['nn_xh_fanbei_max']) show([], config('ToConfig.http_code.error'), lang('limit red maximum most') . ':' . $table_info['nn_xh_fanbei_max']);
                    break;
                case 31://牛牛平倍 闲1
                case 33://牛牛平倍 闲2
                case 35://牛牛平倍 闲3
                    if ($money < $table_info['nn_xh_pingbei_min']) show([], config('ToConfig.http_code.error'), lang('limit red minimum least') . ':' . $table_info['nn_xh_pingbei_min']);
                    if ($money > $table_info['nn_xh_pingbei_max']) show([], config('ToConfig.http_code.error'), lang('limit red maximum most') . ':' . $table_info['nn_xh_pingbei_max']);
                    break;
                case 36://超级闲1
                case 37://超级闲2
                case 38://超级闲3
                    if ($money < $table_info['nn_xh_chaoniu_min']) show([], config('ToConfig.http_code.error'), lang('limit red minimum least') . ':' . $table_info['nn_xh_chaoniu_min']);
                    if ($money > $table_info['nn_xh_chaoniu_max']) show([], config('ToConfig.http_code.error'), lang('limit red maximum most') . ':' . $table_info['nn_xh_chaoniu_max']);
                    break;
                case 40://三公翻倍闲1
                case 42://三公翻倍闲2
                case 44://三公翻倍闲3
                    if ($money < $table_info['sg_xh_fanbei_min']) show([], config('ToConfig.http_code.error'), lang('limit red minimum least') . ':' . $table_info['sg_xh_fanbei_min']);
                    if ($money > $table_info['sg_xh_fanbei_max']) show([], config('ToConfig.http_code.error'), lang('limit red maximum most') . ':' . $table_info['sg_xh_fanbei_max']);
                    break;
                case 41://三公平倍闲1
                case 43://三公平倍闲2
                case 45://三公平倍闲3
                    if ($money < $table_info['sg_xh_pingbei_min']) show([], config('ToConfig.http_code.error'), lang('limit red minimum least') . ':' . $table_info['sg_xh_pingbei_min']);
                    if ($money > $table_info['sg_xh_pingbei_max']) show([], config('ToConfig.http_code.error'), lang('limit red maximum most') . ':' . $table_info['sg_xh_pingbei_max']);
                    break;
                case 46://三公超级闲1
                case 47://三公超级闲2
                case 48://三公超级闲3
                    if ($money < $table_info['sg_xh_chaoniu_min']) show([], config('ToConfig.http_code.error'), lang('limit red minimum least') . ':' . $table_info['sg_xh_chaoniu_min']);
                    if ($money > $table_info['sg_xh_chaoniu_max']) show([], config('ToConfig.http_code.error'), lang('limit red maximum most') . ':' . $table_info['sg_xh_chaoniu_max']);
                    break;
            }
            return true;
        }

        //赔率限红
        if (empty($odds)) return show([], config('ToConfig.http_code.error'), 'please fill in the correct odds id');
        if ($value['money'] < $odds->xian_hong_min) show([], config('ToConfig.http_code.error'), lang('limit red minimum least') . ':' . $odds->xian_hong_min);
        if ($money > $odds->xian_hong_max) show([], config('ToConfig.http_code.error'), lang('limit red maximum most') . ':' . $odds->xian_hong_max);
        return true;
    }

    //当前下单记录
    public function order_current_record()
    {
        $table_id = $this->request->post('id/d', 0);
        if ($table_id <= 0) show([]);

        $records = GameRecords::where([
                'user_id'      => self::$user['id'],
                'table_id'     => $table_id,
                'close_status' => 1,
            ])
            ->field('bet_amt,game_peilv_id,is_exempt')
            ->whereTime('created_at', '-10 minutes')
            ->select();

        $is_exempt = 0;

        foreach ($records as $record) {
            if ($record['is_exempt'] != 0) {
                $is_exempt = $record['is_exempt'];
                break; // 一旦发现免佣，提前结束
            }
        }

        show([
            'is_exempt'   => $is_exempt,
            'record_list' => $records,
        ]);
    }

}