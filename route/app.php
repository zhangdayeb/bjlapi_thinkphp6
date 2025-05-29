<?php
use think\facade\Route;

#荷官操作开始
Route::rule('bjl/get_table/get_data$', '/game.GetForeignTableInfo/get_lz_list');
//露珠列表
Route::rule('bjl/get_table/get_hg_data$', '/game.GetForeignTableInfo/get_hg_lz_list');//露珠
Route::rule('api/diantou/table/getData', '/game.GetForeignTableInfo/get_hg_data_list');//露珠
Route::rule('api/diantou/table/getTableVideo', '/game.GetForeignTableInfo/get_hg_video_list');//露珠

##测试路由开始
Route::rule('api/test/luzhu', '/game.GetForeignTableInfo/testluzhu');//测试露珠
Route::rule('bjl/get_table/post_data_test$', '/game.GetForeignTableInfo/set_post_data_test');//测试发送的数据设置露珠
##测试路由结束

Route::rule('bjl/get_table/get_table_video', '/game.GetForeignTableInfo/get_table_video');
//台桌信息
Route::rule('bjl/get_table/list$', '/game.GetForeignTableInfo/get_table_list');
//统计信息
Route::rule('bjl/get_table/get_table_count$', '/game.GetForeignTableInfo/get_table_count');
//发送的数据设置露珠
Route::rule('bjl/get_table/post_data$', '/game.GetForeignTableInfo/set_post_data');
//删除指定露珠
Route::rule('bjl/get_table/clear_lu_zhu$', '/game.GetForeignTableInfo/lz_delete');
//清除一张台桌露珠
Route::rule('bjl/get_table/clear_lu_zhu_one_table$', '/game.GetForeignTableInfo/lz_table_delete');
//洗牌中设置
Route::rule('bjl/get_table/wash_brand$', '/game.GetForeignTableInfo/get_table_wash_brand');
//设置靴号
Route::rule('bjl/get_table/add_xue$', '/game.GetForeignTableInfo/set_xue_number');
//开局信号
Route::rule('bjl/start/signal$', '/game.GetForeignTableInfo/set_start_signal');
//结束信号
Route::rule('bjl/end/signal$', '/game.GetForeignTableInfo/set_end_signal');
//扑克牌信息
Route::rule('bjl/pai/info$', '/game.GetForeignTableInfo/get_pai_info');

//获取当前台桌
Route::rule('bjl/get_table/table_info$', '/game.GetForeignTableInfo/get_table_info');

#荷官操作结束
Route::rule('bjl/current/record$', '/order.Order/order_current_record');//下注记录回显

//获取扑克牌型
Route::rule('bjl/game/poker$', '/game.GameInfo/get_poker_type');

//用户下注
Route::rule('bjl/bet/order$', '/order.Order/user_bet_order');

Route::rule('/$', '/index/index');
Route::rule('/test$', '/index/test');
Route::miss(function() {
    return show([],404,'无效路由地址');
});