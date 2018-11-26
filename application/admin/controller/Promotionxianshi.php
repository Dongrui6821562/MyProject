<?php

/**
 * 限时折扣
 */

namespace app\admin\controller;

use think\Lang;

class Promotionxianshi extends AdminControl {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'admin/lang/' . config('default_lang') . '/promotionxianshi.lang.php');
    }

    /**
     * 活动列表
     * */
    public function index() {
        //自动开启限时折扣
        if (intval(input('param.promotion_allow')) === 1) {
            $config_model = model('config');
            $update_array = array();
            $update_array['promotion_allow'] = 1;
            $config_model->editConfig($update_array);
        }

        $xianshi_model = model('pxianshi');
        $condition = array();
        if (!empty(input('param.xianshi_name'))) {
            $condition['xianshi_name'] = array('like', '%' . input('param.xianshi_name') . '%');
        }
        if (!empty(input('param.state'))) {
            $condition['xianshi_state'] = intval(input('param.state'));
        }
        $xianshi_list = $xianshi_model->getXianshiList($condition, 10, 'xianshi_state desc, xianshi_end_time desc');
        $this->assign('xianshi_list', $xianshi_list);
        $this->assign('show_page', $xianshi_model->page_info->render());
        $this->assign('xianshi_state_array', $xianshi_model->getXianshiStateArray());

        $this->setAdminCurItem('xianshi_list');
        $this->assign('filtered', $condition ? 1 : 0); //是否有查询条件
        return $this->fetch();
    }

    /**
     * 添加限时折扣活动
     * */
    public function xianshi_add() {
        if (!request()->isPost()) {
            $this->setAdminCurItem('xianshi_add');
            return $this->fetch();
        } else {
            //验证输入
            $xianshi_name = trim(input('post.xianshi_name'));
            $start_time = strtotime(input('post.start_time'));
            $end_time = strtotime(input('post.end_time'));
            $lower_limit = intval(input('post.lower_limit'));
            if ($lower_limit <= 0) {
                $lower_limit = 1;
            }
            if (empty($xianshi_name)) {
                ds_show_dialog(lang('xianshi_name_error'));
            }
            if ($start_time >= $end_time) {
                ds_show_dialog(lang('greater_than_start_time'));
            }


            //生成活动
            $pxianshi_model = model('pxianshi');
            $param = array();
            $param['xianshi_name'] = $xianshi_name;
            $param['xianshi_title'] = input('post.xianshi_title');
            $param['xianshi_explain'] = input('post.xianshi_explain');
            $param['xianshi_starttime'] = $start_time;
            $param['xianshi_end_time'] = $end_time;
            $param['xianshi_lower_limit'] = $lower_limit;
            $result = $pxianshi_model->addXianshi($param);
            if ($result) {
                $this->log(lang('add_limited_time_discount_activity') . $xianshi_name . lang('activity_number') . $result);
                // 添加计划任务
                $this->addcron(array('exetime' => $param['xianshi_end_time'], 'exeid' => $result, 'type' => 7), true);
                dsLayerOpenSuccess(lang('xianshi_add_success'));

            } else {
                ds_show_dialog(lang('xianshi_add_fail'));
            }
        }
    }

    /**
     * 编辑限时折扣活动
     * */
    public function xianshi_edit() {
        if (!request()->isPost()) {
            $pxianshi_model = model('pxianshi');

            $xianshi_info = $pxianshi_model->getXianshiInfoByID(input('param.xianshi_id'));
            if (empty($xianshi_info) || !$xianshi_info['editable']) {
                $this->error(lang('param_error'));
            }

            $this->assign('xianshi_info', $xianshi_info);

            $this->setAdminCurItem('xianshi_edit');
            return $this->fetch('xianshi_add');
        } else {
            $xianshi_id = input('post.xianshi_id');

            $pxianshi_model = model('pxianshi');
            $xianshigoods_model = model('pxianshigoods');

            $xianshi_info = $pxianshi_model->getXianshiInfoByID($xianshi_id);
            if (empty($xianshi_info) || !$xianshi_info['editable']) {
                $this->error(lang('param_error'));
            }

            //验证输入
            $xianshi_name = trim(input('post.xianshi_name'));
            $lower_limit = intval(input('post.lower_limit'));
            if ($lower_limit <= 0) {
                $lower_limit = 1;
            }
            if (empty($xianshi_name)) {
                ds_show_dialog(lang('xianshi_name_error'));
            }

            //生成活动
            $param = array();
            $param['xianshi_name'] = $xianshi_name;
            $param['xianshi_title'] = input('post.xianshi_title');
            $param['xianshi_explain'] = input('post.xianshi_explain');
            $param['xianshi_lower_limit'] = $lower_limit;
            $param_goods = array();
            $param_goods['xianshi_name'] = $xianshi_name;
            $param_goods['xianshi_title'] = input('post.xianshi_title');
            $param_goods['xianshi_explain'] = input('post.xianshi_explain');
            $param_goods['xianshigoods_lower_limit'] = $lower_limit;
            $result = $pxianshi_model->editXianshi($param, array('xianshi_id' => $xianshi_id));
            $result1 = $xianshigoods_model->editXianshigoods($param_goods, array('xianshi_id' => $xianshi_id));
            if ($result && $result) {
                $this->log(lang('edit_limited_time_discount_activity') . $xianshi_name . lang('activity_number') . $xianshi_id);
                dsLayerOpenSuccess(lang('ds_common_op_succ'));
            } else {
                ds_show_dialog(lang('ds_common_op_fail'));
            }
        }
    }

    /**
     * 限时折扣活动取消
     * */
    public function xianshi_cancel() {
        $xianshi_id = intval(input('param.xianshi_id'));
        $xianshi_model = model('pxianshi');
        $result = $xianshi_model->cancelXianshi(array('xianshi_id' => $xianshi_id));
        if ($result) {
            $this->log('取消限时折扣活动，活动编号' . $xianshi_id);
            ds_json_encode(10000, lang('ds_common_op_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_op_fail'));
        }
    }

    /**
     * 限时折扣活动删除
     * */
    public function xianshi_del() {
        $xianshi_model = model('pxianshi');
        $xianshi_id = input('param.xianshi_id');
        $xianshi_id_array = ds_delete_param($xianshi_id);
        if ($xianshi_id_array === FALSE) {
            ds_json_encode(10001, lang('param_error'));
        }
        $condition = array('xianshi_id' => array('in', $xianshi_id_array));
        $result = $xianshi_model->delXianshi($condition);
        if ($result) {
            $this->log('删除限时折扣活动，活动编号' . $xianshi_id);
            ds_json_encode(10000, lang('ds_common_op_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_op_fail'));
        }
    }

    /**
     * 活动详细信息
     * */
    public function xianshi_detail() {
        $xianshi_id = intval(input('param.xianshi_id'));

        $xianshi_model = model('pxianshi');
        $xianshigoods_model = model('pxianshigoods');

        $xianshi_info = $xianshi_model->getXianshiInfoByID($xianshi_id);
        if (empty($xianshi_info)) {
            $this->error(lang('param_error'));
        }
        $this->assign('xianshi_info', $xianshi_info);

        //获取限时折扣商品列表
        $condition = array();
        $condition['xianshi_id'] = $xianshi_id;
        $xianshigoods_list = $xianshigoods_model->getXianshigoodsExtendList($condition, 5);
        $this->assign('xianshigoods_list', $xianshigoods_list);
        $this->assign('show_page', $xianshigoods_model->page_info->render());
        $this->setAdminCurItem('xianshi_detail');
        return $this->fetch();
    }

    /**
     * 选择活动商品
     * */
    public function goods_select() {
        $goods_model = model('goods');
        $condition = array();
        $condition['goods_name'] = array('like', '%' . input('param.goods_name') . '%');
        $goods_list = $goods_model->getGoodsListForPromotion($condition, '*', 10, 'xianshi');

        $this->assign('goods_list', $goods_list);
        $this->assign('show_page', $goods_model->page_info->render());
        echo $this->fetch();
    }

    /**
     * ajax修改抢购信息
     */
    public function ajax() {
        $result = true;
        $update_array = array();
        $where_array = array();

        switch (input('param.branch')) {
            case 'recommend':
                $pxianshigoods_model = model('pxianshigoods');
                $update_array['xianshigoods_recommend'] = input('param.value');
                $where_array['xianshigoods_id'] = input('param.id');
                $result = $pxianshigoods_model->editXianshigoods($update_array, $where_array);
                break;
        }

        if ($result) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }

    /**
     * 限时折扣商品添加
     * */
    public function xianshi_goods_add() {
        $goods_id = intval(input('post.goods_id'));
        $xianshi_id = intval(input('post.xianshi_id'));
        $xianshi_price = floatval(input('post.xianshi_price'));

        $goods_model = model('goods');
        $pxianshi_model = model('pxianshi');
        $xianshigoods_model = model('pxianshigoods');

        $data = array();
        $data['result'] = true;

        $goods_info = $goods_model->getGoodsInfoByID($goods_id);
        if (empty($goods_info)) {
            $data['result'] = false;
            $data['message'] = lang('param_error');
            echo json_encode($data);
            die;
        }

        $xianshi_info = $pxianshi_model->getXianshiInfoByID($xianshi_id);
        if (!$xianshi_info) {
            $data['result'] = false;
            $data['message'] = lang('param_error');
            echo json_encode($data);
            die;
        }

        //检查商品是否已经参加同时段活动
        $condition = array();
        $condition['xianshigoods_end_time'] = array('gt', $xianshi_info['xianshi_starttime']);
        $condition['goods_id'] = $goods_id;
        $xianshigoods = $xianshigoods_model->getXianshigoodsExtendList($condition);
        if (!empty($xianshigoods)) {
            $data['result'] = false;
            $data['message'] = lang('product_participated_simultaneous_activities');
            echo json_encode($data);
            die;
        }

        //添加到活动商品表
        $param = array();
        $param['xianshi_id'] = $xianshi_info['xianshi_id'];
        $param['xianshi_name'] = $xianshi_info['xianshi_name'];
        $param['xianshi_title'] = $xianshi_info['xianshi_title'];
        $param['xianshi_explain'] = $xianshi_info['xianshi_explain'];
        $param['goods_id'] = $goods_info['goods_id'];
        $param['goods_name'] = $goods_info['goods_name'];
        $param['goods_price'] = $goods_info['goods_price'];
        $param['xianshigoods_price'] = $xianshi_price;
        $param['goods_image'] = $goods_info['goods_image'];
        $param['xianshigoods_starttime'] = $xianshi_info['xianshi_starttime'];
        $param['xianshigoods_end_time'] = $xianshi_info['xianshi_end_time'];
        $param['xianshigoods_lower_limit'] = $xianshi_info['xianshi_lower_limit'];

        $result = array();
        $xianshigoods_info = $xianshigoods_model->addXianshigoods($param);
        if ($xianshigoods_info) {
            $result['result'] = true;
            $data['message'] = lang('add_success');
            $data['xianshi_goods'] = $xianshigoods_info;
            $this->log(lang('add_limited_time_discount_items') . $xianshi_info['xianshi_name'] . '，' . lang('ds_goods_name') . '：' . $goods_info['goods_name']);

            // 添加任务计划
            $this->addcron(array('type' => 2, 'exeid' => $goods_info['goods_id'], 'exetime' => $param['xianshigoods_starttime']));
        } else {
            $data['result'] = false;
            $data['message'] = lang('param_error');
        }
        echo json_encode($data);
        die;
    }

    /**
     * 限时折扣商品价格修改
     * */
    public function xianshi_goods_price_edit() {
        $xianshigoods_id = intval(input('post.xianshigoods_id'));
        $xianshi_price = floatval(input('post.xianshi_price'));

        $data = array();
        $data['result'] = true;

        $xianshigoods_model = model('pxianshigoods');

        $xianshigoods_info = $xianshigoods_model->getXianshigoodsInfoByID($xianshigoods_id);
        if (!$xianshigoods_info) {
            $data['result'] = false;
            $data['message'] = lang('param_error');
            echo json_encode($data);
            die;
        }

        $update = array();
        $update['xianshigoods_price'] = $xianshi_price;
        $condition = array();
        $condition['xianshigoods_id'] = $xianshigoods_id;
        $result = $xianshigoods_model->editXianshigoods($update, $condition);

        if ($result) {
            $xianshigoods_info['xianshigoods_price'] = $xianshi_price;
            $xianshigoods_info = $xianshigoods_model->getXianshigoodsExtendInfo($xianshigoods_info);
            $data['xianshi_price'] = $xianshigoods_info['xianshigoods_price'];
            $data['xianshi_discount'] = $xianshigoods_info['xianshi_discount'];

            // 添加对列修改商品促销价格
            \mall\queue\QueueClient::push('updateGoodsPromotionPriceByGoodsId', $xianshigoods_info['goods_id']);

            $this->log(lang('limited_time_discount_price_modified') . $xianshigoods_info['xianshigoods_price'] . '，' . lang('ds_goods_name') . '：' . $xianshigoods_info['goods_name']);
        } else {
            $data['result'] = false;
            $data['message'] = lang('ds_common_op_succ');
        }
        echo json_encode($data);
        die;
    }

    /**
     * 限时折扣商品删除
     * */
    public function xianshi_goods_delete() {
        $xianshigoods_model = model('pxianshigoods');
        $pxianshi_model = model('pxianshi');

        $data = array();
        $data['result'] = true;

        $xianshigoods_id = intval(input('post.xianshigoods_id'));
        $xianshigoods_info = $xianshigoods_model->getXianshigoodsInfoByID($xianshigoods_id);
        if (!$xianshigoods_info) {
            $data['result'] = false;
            $data['message'] = lang('param_error');
            echo json_encode($data);
            die;
        }

        $xianshi_info = $pxianshi_model->getXianshiInfoByID($xianshigoods_info['xianshi_id']);
        if (!$xianshi_info) {
            $data['result'] = false;
            $data['message'] = lang('param_error');
            echo json_encode($data);
            die;
        }

        if (!$xianshigoods_model->delXianshigoods(array('xianshigoods_id' => $xianshigoods_id))) {
            $data['result'] = false;
            $data['message'] = lang('xianshi_goods_delete_fail');
            echo json_encode($data);
            die;
        }

        // 添加对列修改商品促销价格
        \mall\queue\QueueClient::push('updateGoodsPromotionPriceByGoodsId', $xianshigoods_info['goods_id']);

        $this->log(lang('delete_time_limited_discount_items') . $xianshi_info['xianshi_name'] . '，' . lang('ds_goods_name') . '：' . $xianshigoods_info['goods_name']);
        echo json_encode($data);
        die;
    }

    /**
     * 页面内导航菜单
     *
     * @param string $menu_key 当前导航的menu_key
     * @param array $array 附加菜单
     * @return
     */
    protected function getAdminItemList() {
        $menu_array = array(
            array(
                'name' => 'xianshi_list', 'text' => lang('xianshi_list'), 'url' => url('Promotionxianshi/index')
            ),
            array(
                'name' => 'xianshi_add', 'text' => lang('xianshi_add'), 'url' => url('Promotionxianshi/xianshi_add')
            ),
        );
        if (request()->action() == 'xianshi_detail')
            $menu_array[] = array(
                'name' => 'xianshi_detail', 'text' => lang('xianshi_detail'),
                'url' => url('Promotionxianshi/xianshi_detail')
            );
        return $menu_array;
    }

}
