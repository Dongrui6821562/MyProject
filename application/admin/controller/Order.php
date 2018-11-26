<?php

namespace app\admin\controller;

use think\Lang;
use think\Validate;

class Order extends AdminControl
{

    const EXPORT_SIZE = 1000;

    public function _initialize()
    {
        parent::_initialize();
        Lang::load(APP_PATH . 'admin/lang/' . config('default_lang') . '/order.lang.php');
    }

    public function index()
    {
        $order_model = model('order');
        $condition = array();

        $order_sn = input('param.order_sn');
        if ($order_sn) {
            $condition['order_sn'] = $order_sn;
        }
        $order_state = input('param.order_state');
        if (in_array($order_state, array('0', '10', '20', '30', '40'))) {
            $condition['order_state'] = $order_state;
        }
        $payment_code = input('param.payment_code');
        if ($payment_code) {
            $condition['payment_code'] = $payment_code;
        }
        $buyer_name = input('param.buyer_name');
        if ($buyer_name) {
            $condition['buyer_name'] = $buyer_name;
        }
        $query_start_time = input('param.query_start_time');
        $query_end_time = input('param.query_end_time');
        $if_start_time = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_start_time);
        $if_end_time = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_end_time);
        $start_unixtime = $if_start_time ? strtotime($query_start_time) : null;
        $end_unixtime = $if_end_time ? strtotime($query_end_time) : null;
        if ($start_unixtime || $end_unixtime) {
            $condition['add_time'] = array('between', array($start_unixtime, $end_unixtime));
        }
        $order_list = $order_model->getOrderList($condition, 10);
        $this->assign('show_page', $order_model->page_info->render());

        foreach ($order_list as $order_id => $order_info) {
            //显示取消订单
            $order_list[$order_id]['if_cancel'] = $order_model->getOrderOperateState('system_cancel', $order_info);
            //显示调整运费
            $order_list[$order_id]['if_modify_price'] = $order_model->getOrderOperateState('modify_price', $order_info);
            //显示收到货款
            $order_list[$order_id]['if_system_receive_pay'] = $order_model->getOrderOperateState('system_receive_pay', $order_info);
            //显示调整价格
            $order_list[$order_id]['if_spay_price'] = $order_model->getOrderOperateState('spay_price', $order_info);
            //显示发货状态
            $order_list[$order_id]['if_send'] = $order_model->getOrderOperateState('send', $order_info);
        }
        //显示支付接口列表(搜索)
        $payment_list = model('payment')->getPaymentOpenList();
        $this->assign('payment_list', $payment_list);
        $this->assign('order_list', $order_list);

        $this->assign('filtered', $condition ? 1 : 0); //是否有查询条件
        $this->setAdminCurItem('add');
        return $this->fetch('index');
    }

    /**
     * 查看订单
     */
    public function order_print()
    {
        $order_id = intval(input('param.order_id'));
        if ($order_id <= 0) {
            $this->error(lang('wrong_argument'));
        }
        $order_model = model('order');
        $condition['order_id'] = $order_id;
        $order_info = $order_model->getOrderInfo($condition, array('order_common', 'order_goods'));
        if (empty($order_info)) {
            $this->error(lang('member_printorder_ordererror'));
        }
        $this->assign('order_info', $order_info);


        //订单商品
        $condition = array();
        $condition['order_id'] = $order_id;
        $goods_new_list = array();
        $goods_all_num = 0;
        $goods_total_price = 0;
        if (isset($order_info['extend_order_goods']) && !empty($order_info['extend_order_goods'])) {
            $i = 1;
            foreach ($order_info['extend_order_goods'] as $k => $v) {
                $v['goods_name'] = str_cut($v['goods_name'], 100);
                $goods_all_num += $v['goods_num'];
                $v['goods_all_price'] = ds_price_format($v['goods_num'] * $v['goods_price']);
                $goods_total_price += $v['goods_all_price'];
                $goods_new_list[ceil($i / 15)][$i] = $v;
                $i++;
            }
        }
        //优惠金额
        $promotion_amount = $goods_total_price - $order_info['goods_amount'];
        //运费
        $order_info['shipping_fee'] = $order_info['shipping_fee'];
        $this->assign('promotion_amount', $promotion_amount);
        $this->assign('goods_all_num', $goods_all_num);
        $this->assign('goods_total_price', ds_price_format($goods_total_price));
        $this->assign('goods_list', $goods_new_list);

        $this->setAdminCurItem();
        return $this->fetch('order_print');
    }

    /**
     * 平台订单状态操作
     *
     */
    public function change_state()
    {
        $order_id = intval(input('param.order_id'));
        if ($order_id <= 0) {
            $this->error(lang('miss_order_number'));
        }
        $order_model = model('order');

        //获取订单详细
        $condition = array();
        $condition['order_id'] = $order_id;
        $order_info = $order_model->getOrderInfo($condition);

        $state_type = input('param.type_state');
        if ($state_type == 'cancel') {
            $result = $this->_order_cancel($order_info);
        } elseif ($state_type == 'spay_price') {
            //修改商品价格
            $result = $this->_order_spay_price($order_info, $_POST);
        } elseif ($state_type == 'modify_price') {
            //修改商品运费
            $result = $this->_order_ship_price($order_info, $_POST);
        } elseif ($state_type == 'receive_pay') {
            $result = $this->_order_receive_pay($order_info, $_POST);
        }
        if (!$result['code']) {
            $this->error($result['msg']);
        } else {
            dsLayerOpenSuccess($result['msg']);
        }
    }


    /**
     * 系统取消订单
     */
    private function _order_cancel($order_info)
    {
        $order_model = model('order');
        $logic_order = model('order', 'logic');
        $if_allow = $order_model->getOrderOperateState('system_cancel', $order_info);
        if (!$if_allow) {
            return ds_callback(false, '无权操作');
        }
        $result = $logic_order->changeOrderStateCancel($order_info, 'system', $this->admin_info['admin_name']);
        if ($result['code']) {
            $this->log(lang('order_log_cancel') . ',' . lang('order_number') . ':' . $order_info['order_sn'], 1);
        }
        return $result;
    }

    /**
     * 修改发货地址
     */
    private function _edit_order_daddress($daddress_id, $order_id)
    {
        $order_model = model('order');
        $data = array();
        $data['daddress_id'] = intval($daddress_id);
        $condition = array();
        $condition['order_id'] = $order_id;
        return $order_model->editOrdercommon($data, $condition);
    }

    /**
     * 修改商品价格
     * @param unknown $order_info
     */
    private function _order_spay_price($order_info, $post)
    {
        $order_model = model('order');
        $logic_order = model('order', 'logic');
        if (!request()->isPost()) {
            $this->assign('order_info', $order_info);
            $this->assign('order_id', $order_info['order_id']);
            echo $this->fetch('edit_spay_price');
            exit();
        } else {
            $if_allow = $order_model->getOrderOperateState('spay_price', $order_info);
            if (!$if_allow) {
                return ds_callback(false, '无权操作');
            }
            return $logic_order->changeOrderSpayPrice($order_info, 'seller', session('member_name'), $post['goods_amount']);
        }
    }

    /**
     * 修改运费
     * @param unknown $order_info
     */
    private function _order_ship_price($order_info, $post)
    {
        $order_model = model('order');
        $logic_order = model('order', 'logic');
        if (!request()->isPost()) {
            $this->assign('order_info', $order_info);
            $this->assign('order_id', $order_info['order_id']);
            echo $this->fetch('edit_price');
            exit();
        } else {
            $if_allow = $order_model->getOrderOperateState('modify_price', $order_info);
            if (!$if_allow) {
                return ds_callback(false, '无权操作');
            }
            return $logic_order->changeOrderShipPrice($order_info, 'seller', session('member_name'), $post['shipping_fee']);
        }
    }


    /**
     * 系统收到货款
     * @throws Exception
     */
    private function _order_receive_pay($order_info, $post)
    {
        $order_id = $order_info['order_id'];
        $order_model = model('order');
        $logic_order = model('order', 'logic');
        $if_allow = $order_model->getOrderOperateState('system_receive_pay', $order_info);
        if (!$if_allow) {
            return ds_callback(false, '无权操作');
        }

        if (!request()->isPost()) {
            $this->assign('order_info', $order_info);
            //显示支付接口列表
            $payment_list = model('payment')->getPaymentOpenList();
            //去掉预存款和货到付款
            foreach ($payment_list as $key => $value) {
                if ($value['payment_code'] == 'predeposit' || $value['payment_code'] == 'offline') {
                    unset($payment_list[$key]);
                }
            }
            $this->assign('payment_list', $payment_list);
            $this->setAdminCurItem('receive_pay');
            echo $this->fetch('receive_pay');
            exit;
        } else {
            $order_list = $order_model->getOrderList(array('pay_sn' => $order_info['pay_sn'], 'order_state' => ORDER_STATE_NEW));
            $result = $logic_order->changeOrderReceivePay($order_list, 'system', $this->admin_info['admin_name'], $post);
            if ($result['code']) {
                $this->log('将订单改为已收款状态,' . lang('order_number') . ':' . $order_info['order_sn'], 1);
            }
            return $result;
        }
    }

    /**
     * 查看订单
     *
     */
    public function show_order()
    {
        $order_id = intval(input('param.order_id'));
        if ($order_id <= 0) {
            $this->error(lang('miss_order_number'));
        }
        $order_model = model('order');
        $order_info = $order_model->getOrderInfo(array('order_id' => $order_id), array('order_goods', 'order_common'));

        //订单变更日志
        $log_list = $order_model->getOrderlogList(array('order_id' => $order_info['order_id']));
        $this->assign('order_log', $log_list);

        //退款退货信息
        $refundreturn_model = model('refundreturn');
        $condition = array();
        $condition['order_id'] = $order_info['order_id'];
        $condition['admin_time'] = array('gt', 0);
        $return_list = $refundreturn_model->getReturnList($condition);
        $this->assign('return_list', $return_list);

        //退款信息
        $refund_list = $refundreturn_model->getRefundList($condition);
        $this->assign('refund_list', $refund_list);

        //卖家发货信息
        if (!empty($order_info['extend_order_common']['daddress_id'])) {
            $daddress_info = model('daddress')->getAddressInfo(array('daddress_id' => $order_info['extend_order_common']['daddress_id']));
            $this->assign('daddress_info', $daddress_info);
        }
        $this->assign('order_info', $order_info);
        return $this->fetch('show_order');
    }

    /**
     * 导出
     *
     */
    public function export_step1()
    {

        $order_model = model('order');
        $condition = array();
        $order_sn = input('param.order_sn');
        if ($order_sn) {
            $condition['order_sn'] = $order_sn;
        }
        $order_state = input('param.order_state');
        if (in_array($order_state, array('0', '10', '20', '30', '40'))) {
            $condition['order_state'] = $order_state;
        }
        $payment_code = input('param.payment_code');
        if ($payment_code) {
            $condition['payment_code'] = $payment_code;
        }
        $buyer_name = input('param.buyer_name');
        if ($buyer_name) {
            $condition['buyer_name'] = $buyer_name;
        }
        $query_start_time = input('param.query_start_time');
        $query_end_time = input('param.query_end_time');
        $if_start_time = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_start_time);
        $if_end_time = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_end_time);
        $start_unixtime = $if_start_time ? strtotime($query_start_time) : null;
        $end_unixtime = $if_end_time ? strtotime($query_end_time) : null;
        if ($start_unixtime || $end_unixtime) {
            $condition['add_time'] = array('between', array($start_unixtime, $end_unixtime));
        }

        if (!is_numeric(input('param.curpage'))) {
            $count = $order_model->getOrderCount($condition);
            $export_list = array();
            if ($count > self::EXPORT_SIZE) { //显示下载链接
                $page = ceil($count / self::EXPORT_SIZE);
                for ($i = 1; $i <= $page; $i++) {
                    $limit1 = ($i - 1) * self::EXPORT_SIZE + 1;
                    $limit2 = $i * self::EXPORT_SIZE > $count ? $count : $i * self::EXPORT_SIZE;
                    $export_list[$i] = $limit1 . ' ~ ' . $limit2;
                }
                $this->assign('export_list', $export_list);
                return $this->fetch('/public/excel');
            } else { //如果数量小，直接下载
                $data = $order_model->getOrderList($condition, '', '*', 'order_id desc', self::EXPORT_SIZE);
                $this->createExcel($data);
            }
        } else { //下载
            $limit1 = (input('param.curpage') - 1) * self::EXPORT_SIZE;
            $limit2 = self::EXPORT_SIZE;
            $data = $order_model->getOrderList($condition, '', '*', 'order_id desc', "{$limit1},{$limit2}");
            $this->createExcel($data);
        }
    }

    /**
     * 发货
     */
    public function send()
    {
        $order_id = input('param.order_id');
        if ($order_id <= 0) {
            $this->error(lang('wrong_argument'));
        }

        $order_model = model('order');
        $condition = array();
        $condition['order_id'] = $order_id;
        $order_info = $order_model->getOrderInfo($condition, array('order_common', 'order_goods'));
        $if_allow_send = intval($order_info['lock_state']) || !in_array($order_info['order_state'], array(ORDER_STATE_PAY, ORDER_STATE_SEND));
        if ($if_allow_send) {
            $this->error(lang('wrong_argument'));
        }

        if (!request()->isPost()) {
            $this->assign('order_info', $order_info);
            //取发货地址
            $daddress_model = model('daddress');
            $daddress_info = array();
            if ($order_info['extend_order_common']['daddress_id'] > 0) {
                $daddress_info = $daddress_model->getAddressInfo(array('daddress_id' => $order_info['extend_order_common']['daddress_id']));
            }
            if (empty($daddress_info)) {
                //取默认地址
                $daddress_info = $daddress_model->getAddressList(array(), '*', 'daddress_isdefault desc', 1);
                if (!empty($daddress_info)) {
                    $daddress_info = $daddress_info[0];
                    //写入发货地址编号
                    $this->_edit_order_daddress($daddress_info['daddress_id'], $order_id);
                } else {
                    //写入发货地址编号
                    $this->_edit_order_daddress(0, $order_id);
                }
            }
            $this->assign('daddress_info', $daddress_info);

            //如果是自提订单
            $express_list = rkcache('express', true);


            $this->assign('express_list', $express_list);

            return $this->fetch('send');
        } else {
            $logic_order = model('order', 'logic');
            $_POST['reciver_info'] = $this->_get_reciver_info();
            $result = $logic_order->changeOrderSend($order_info, 'seller', session('member_name'), $_POST);
            if (!$result['code']) {
                $this->error($result['msg']);
            } else {
                ds_show_dialog($result['msg'], url('Sellerorder/index'), 'succ');
            }
        }
    }

    /**
     * 生成excel
     *
     * @param array $data
     */
    private function createExcel($data = array())
    {
        Lang::load(APP_PATH . 'admin/lang/' . config('default_lang') . '/export.lang.php');
        $excel_obj = new \excel\Excel();
        $excel_data = array();
        //设置样式
        $excel_obj->setStyle(array('id' => 's_title', 'Font' => array('FontName' => '宋体', 'Size' => '12', 'Bold' => '1')));
        //header
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('order_number'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('exp_od_store'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('buyer_name'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('order_time'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('order_total_transport'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('order_total_transport'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('exp_od_paytype'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('order_state'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('exp_od_buyerid'));
        $excel_data[0][] = array('styleid' => 's_title', 'data' => lang('exp_od_bemail'));
        //data
        foreach ((array)$data as $k => $v) {
            $tmp = array();
            $tmp[] = array('data' => 'DS' . $v['order_sn']);
            $tmp[] = array('data' => $v['buyer_name']);
            $tmp[] = array('data' => date('Y-m-d H:i:s', $v['add_time']));
            $tmp[] = array('format' => 'Number', 'data' => ds_price_format($v['order_amount']));
            $tmp[] = array('format' => 'Number', 'data' => ds_price_format($v['shipping_fee']));
            $tmp[] = array('data' => get_order_payment_name($v['payment_code']));
            $tmp[] = array('data' => get_order_state($v));
            $tmp[] = array('data' => $v['buyer_id']);
            $tmp[] = array('data' => $v['buyer_email']);
            $excel_data[] = $tmp;
        }
        $excel_data = $excel_obj->charset($excel_data, CHARSET);
        $excel_obj->addArray($excel_data);
        $excel_obj->addWorksheet($excel_obj->charset(lang('ds_orders'), CHARSET));
        $excel_obj->generateXML($excel_obj->charset(lang('ds_orders'), CHARSET) . input('param.curpage') . '-' . date('Y-m-d-H', time()));
    }

}
