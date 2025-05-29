<?php

namespace app\model;

use think\Model;

class LuzhuRes extends Model
{

    public static function LuZhuList($params){
        $map = array();
        $map['status'] = 1;
        $map['table_id'] = $params['tableId'];
        if (isset($params['xue']) && $params['xue'] > 0) $map['xue_number'] = $params['xue'];
        
        // 之前的
        // $date = date('Y-m-d');
        // if(time() > strtotime(date('Y-m-d').' 09:00:00')){
        //     $date = date('Y-m-d'.' 09:00:00');
        // }
        
        // 调整后的
        $nowTime = time(); // 当前时间
        $startTime = strtotime(date("Y-m-d 09:00:00", time())); // 今日9点
        // 如果小于，则算前一天的
        if ($nowTime < $startTime) {
            $startTime = $startTime - (24 * 60 * 60);
        } else {
            // 保持不变 这样做到 自动更新 露珠
        }
        $date = $startTime;
        
        // 增加靴号的重新处理
        if(!isset($map['xue_number'])){
            $one_info = self::where($map)->whereTime('create_time','>', $date)->order('id desc')->find();
            !empty($one_info) &&  $map['xue_number'] = $one_info->xue_number;
        }

        $map['game_type'] = isset($params['gameType']) && !empty($params['gameType']) ? $params['gameType'] : 3; // 代表百家乐 | 龙虎
        $limit = 66;
        if($map['game_type'] == 2){
            $limit = 180;
        }
        $returnData = array();
       
        $info = self::whereTime('create_time','>', $date)->cache('luzhuinfo_'.$map['table_id'],60)->where('result','<>',0)->where($map)->order('id asc')->limit($limit)->select();
       
        // 发给前台的 数据
        $i = 0;
        foreach ($info as $k => $val) {
            $tmp = array();
            $t = explode("|", $val['result']);
            $tmp['result'] = $t[0];
            $tmp['ext'] = $t[1];
            if ($tmp['result'] != 0) {
                $k = 'k' . $i;
                $returnData[$k] = $tmp;
                $i++;
            }
        }
        return $returnData;
    }
    
    public static function LuZhutest($params){
        $map = array();
        $map['status'] = 1;
        $map['table_id'] = $params['tableId'];
        if (isset($params['xue']) && $params['xue'] > 0) $map['xue_number'] = $params['xue'];

        // 增加靴号的重新处理
         //if()
        if(!isset($map['xue_number'])){
            $one_info = self::where($map)->whereTime('create_time', 'today')->order('id desc')->find();
            !empty($one_info) &&  $map['xue_number'] = $one_info->xue_number;
        }

        $map['game_type'] = isset($params['gameType']) && !empty($params['gameType']) ? $params['gameType'] : 3; // 代表百家乐 | 龙虎
        $limit = 66;
        if($map['game_type'] == 2){
            $limit = 180;
        }
        $returnData = array();
        
        $date = date('Y-m-d');
        if(time() > strtotime(date('Y-m-d'.' 09:00:00'))){
            $date = date('Y-m-d'.' 09:00:00');
        }

        $info = self::whereTime('create_time','>', $date)
        ->where('result','<>',0)
        ->where($map)
        ->cache('luzhuinfo_'.$map['table_id'],60)
        ->order('id asc')
        ->limit($limit)
        ->select();
        // echo  self::getLastSql();die;
        // 发给前台的 数据
        $i = 0;
        foreach ($info as $k => $val) {
            $tmp = array();
            $t = explode("|", $val['result']);
            $tmp['result'] = $t[0];
            $tmp['ext'] = $t[1];
            if ($tmp['result'] != 0) {
                $k = 'k' . $i;
                $returnData[$k] = $tmp;
                $i++;
            }
        }
        return $returnData;
    }
}