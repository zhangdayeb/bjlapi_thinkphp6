<?php


namespace app\service;

/**
 *  百家乐
 *  扑克牌点子
 *   使用方法  单独分开写方便后面 需要对某方法进行修改什么的
 *    可直接调用 runs函数 执行所有，传入开牌结果
 * 1 先调用  数据库拿出来的参数转为数组形式在传入 calculation方法 得到整理出来的数据
 * 2 得到  calculation方法的值  在传入 到 calculation_start方法  得到庄家闲家的点数
 * 3 得到  calculation_start方法的值 传入到 最后的计算方法 calculation_result 获得最后的结果
 */
class OpenPaiCalculationService
{
    //执行所有的函数
    public function runs(array $pai): array
    {
        $calculation_start = $this->calculation_start($this->calculation($pai));
        return $this->calculation_result($calculation_start);
    }

    /**
     * 整理出所有数据
     * @param $pai /数组形式的结果数据
     * @return array
     */
    public function calculation($pai): array
    {
        //整理出所有数据
        $data = [];
        foreach ($pai as $key => $value) {
            $key = intval($key);
            $data[$key] = explode('|', $value);
            $data[$key][0] = intval($data[$key][0]);
            //$data[$key][1] = $data[$key][1];
        }
        return $data;
    }

    /**
     * /庄家点数和闲加点数，以及庄闲对
     * @param $data /是整理好的数据 $data
     * 通过 calculation整理出来的data
     */
    public function calculation_start(array $data): array
    {
        $luckySize = 2; // 默认幸运6的大牌数字  新增代码 niu
        $size = 0; //结果是大还是小 1大 0小
        $zhuang_dui = false; //是否是庄对
        $xian_dui = false; //是否是闲对
        $lucky = 0;
        $zhuang_string = '';//庄家牌点数
        $zhuang_count = 0;//庄家牌数量
        $xian_string = '';//闲家牌点数
        $xian_count = 0;//闲家牌数量
        $zhuang_point = 0;//庄家点数
        //$zhuang_point = $data[1][0] + $data[2][0];//庄家点数
        //$xian_point = $data[3][0] + $data[4][0]; //闲家点数
        $xian_point = 0; //闲家点数
        //判断是大还是小

        // 判读幸运 6 的大小  新增代码 niu
        if ($data[1][0] != 0) {
            $luckySize = 3;
        }
        //庄是否是对子 相等
        if ($data[2][0] === $data[3][0]) {
            $zhuang_dui = true;
        }
        //闲是否是对子 相等
        if ($data[4][0] === $data[5][0]) {
            $xian_dui = true;
        }
        $flower = ['r' => '红桃', 'h' => '黑桃', 'f' => '方块', 'm' => '梅花'];
        foreach ($data as $key => $value) {
            #开始   有几张牌，需要在 覆盖9点以上牌为0点之前计算
            //$size 大于等于2 就是小了
            if ($value[0] == 0) {
                $size++;
            }

            ##########每张牌的数字 为了翻译牌
            if ($value[0] > 0) {
                $pai = $value[0];
                $pai_flower = $flower[$value[1]];
                if ($value[0] == 1) {
                    $pai = 'A';
                }
                if ($value[0] == 11) {
                    $pai = 'J';
                }
                if ($value[0] == 12) {
                    $pai = 'Q';
                }
                if ($value[0] == 13) {
                    $pai = 'K';
                }

                if ($key == 1 || $key == 2 || $key == 3) {
                    $zhuang_string .= $pai_flower . $pai . '-';
                } elseif ($key == 4 || $key == 5 || $key == 6) {
                    $xian_string .= $pai_flower . $pai . '-';
                }

            }
            ###############

            //大于9点的  都是0点
            if ($value[0] > 9) {
                $value[0] = 0;
            }
            #结束   计算对子和 有几张牌，需要在 覆盖9点以上牌为0点之前计算

            //庄家点数
            if ($key == 1 || $key == 2 || $key == 3) {
                $zhuang_point += $value[0];
                if ($value[0] != 0) {
                    $zhuang_count++;
                }
            }

            //闲家点数
            if ($key == 4 || $key == 5 || $key == 6) {
                $xian_point += $value[0];
                if ($value[0] != 0) {
                    $xian_count++;
                }
            }

            //判断是否是幸运6   点数
            if ($key == 1 || $key == 2 || $key == 3) {
                $lucky += $value[0];// 6点就是幸运 6
            }


        }

        // 0 小赢  1 大赢
        if ($size < 2) {
            $size = 1;
        } else {
            $size = 0;
        }

        return [
            'luckySize' => $luckySize,
            'size' => $size,
            'zhuang_point' => $zhuang_point,
            'xian_point' => $xian_point,
            'zhuang_dui' => $zhuang_dui,
            'xian_dui' => $xian_dui,
            'lucky' => $lucky,
            'zhuang_count' => $zhuang_count,
            'xian_count' => $xian_count,
            'zhuang_string' => $zhuang_string,
            'xian_string' => $xian_string,
        ];
    }

    /**
     * 计算 庄 闲 和 结果
     * @param $res ['zhuang_point','xian_point','zhuang_dui','xian_dui','win','size','lucky']
     * #返回参数
     * zhuang_point 庄点数
     * xian_point 闲点数
     * zhuang_dui 是否庄对
     * xian_dui 是否闲对
     * win 主结果 =1 庄赢  =2 闲赢 =3 和牌   0错误
     * size  大赢还是小赢 0 小赢   1大赢
     * lucky 是否是幸运6   0不是  大于0是幸运6
     */
    public function calculation_result(array $res): array
    {
        //取余后算主结果
        $res['zhuang_point'] = $res['zhuang_point'] % 10;
        $res['xian_point'] = $res['xian_point'] % 10;
        $res['lucky'] = $res['lucky'] % 10;
        //主结果 =1 庄赢  =2 闲赢 =3 和牌   0错误
        $win = 0;
        $lucky = 0;//是否是幸运6 0不是，1是
        if (intval($res['zhuang_point']) === intval($res['xian_point'])) {
            //和牌
            $win = 3;
        } elseif (intval($res['zhuang_point']) > intval($res['xian_point'])) {
            //庄赢
            $win = 1;
            //庄赢并且 等于6 是幸运6
            if (intval($res['lucky']) === 6) {
                $lucky = 1;
            }
        } elseif (intval($res['zhuang_point']) < intval($res['xian_point'])) {
            //闲赢
            $win = 2;
        }

        $res['win'] = $win;
        $res['lucky'] = $lucky;
        return $res;
    }


    /**
     * @param $resId /购买的牌赔率 ID
     * @param $paiInfo /牌中奖结果
     */
    public function user_win_or_not(int $resId, array $paiInfo): bool
    {

        switch ($resId) {
            case 1://是否是 大
                if ($paiInfo['size'] == 1) {
                    return true;
                } elseif ($paiInfo['size'] == 0) {
                    return false;
                }
                return false;
                break;
            case 2://是否是 闲对
                return $paiInfo['xian_dui'];
                break;
            case 3://是否是 幸运
                if ($paiInfo['lucky'] == 1) {
                    return true;
                } elseif ($paiInfo['lucky'] == 0) {
                    return false;
                }
                return false;
                break;
            case 4://是否是 庄对
                return $paiInfo['zhuang_dui'];
                break;
            case 5://是否是 小
                if ($paiInfo['size'] == 0) {
                    return true;
                } elseif ($paiInfo['size'] == 1) {
                    return false;
                }
                return false;
                break;
            case 6://是否是 闲
                //win 主结果 =1 庄赢  =2 闲赢 =3 和牌   0错误
                if ($paiInfo['win'] == 2) {
                    return true;
                }
                return false;
                break;
            case 7://是否是 和
                if ($paiInfo['win'] == 3) {
                    return true;
                }
                return false;
                break;
            case 8://是否是 庄
                if ($paiInfo['win'] == 1) {
                    return true;
                }
                return false;
                break;
            default:
                return false;
                break;
        }
    }

    //用户购买的结果转汉字
    public function user_pai_chinese(int $res): string
    {
        $res = intval($res);
        $pai = [1 => '大', 2 => '闲对', 3 => '幸运', 4 => '庄对', 5 => '小', 6 => '闲', 7 => '和', 8 => '庄'];
        return $pai[$res];
    }

    //开牌结果转汉字
    public function pai_chinese(array $paiInfo): string
    {
        $string = '';
        if ($paiInfo['size'] == 0) {
            $string .= '小|';
        } elseif ($paiInfo['size'] == 1) {
            $string .= '大|';
        }
        if ($paiInfo['zhuang_dui'] == true) {
            $string .= '庄对|';
        }

        if ($paiInfo['xian_dui'] == true) {
            $string .= '闲对|';
        }
        if ($paiInfo['lucky'] > 0) {
            $string .= '幸运6|';
        }
        if ($paiInfo['win'] == 1) {
            $string .= '庄赢|';
        } elseif ($paiInfo['win'] == 2) {
            $string .= '闲赢|';
        } elseif ($paiInfo['win'] == 3) {
            $string .= '和牌|';
        }
        return $string;
    }

    //开牌结果转汉字
    public function pai_info(array $paiInfo): array
    {
        if (empty($paiInfo)) return ['z' => '庄:', 'x' => '闲:'];
        return ['z' => '庄:' . $paiInfo['zhuang_string'] . '  ', 'x' => '闲:' . $paiInfo['xian_string']];
    }

    //开牌结果转汉字
    public function pai_flash(array $paiInfo): array
    {
        $map = [];
        if ($paiInfo['size'] == 0) {
            $map[] = 5;
        } elseif ($paiInfo['size'] == 1) {
            $map[] = 1;
        }
        if ($paiInfo['zhuang_dui'] == true) {
            $map[] = 4;
        }

        if ($paiInfo['xian_dui'] == true) {
            $map[] = 2;
        }
        if ($paiInfo['lucky'] > 0) {
            $map[] = 3;
        }
        if ($paiInfo['win'] == 1) {
            $map[] = 8;
        } elseif ($paiInfo['win'] == 2) {
            $map[] = 6;
        } elseif ($paiInfo['win'] == 3) {
            $map[] = 7;
        }
        return $map;
    }
}