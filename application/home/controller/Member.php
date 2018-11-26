<?php

namespace app\home\controller;

use think\Lang;

class Member extends BaseMember {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/'.config('default_lang').'/member.lang.php');
    }

    public function index() {
        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('selleralbum');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('my_album');
        return $this->fetch($this->template_dir . 'index');
    }

    public function ajax_load_member_info() {
        $member_info = $this->member_info;
        $member_info['security_level'] = model('member')->getMemberSecurityLevel($member_info);

        //代金券数量
        $member_info['voucher_count'] = model('voucher')->getCurrentAvailableVoucherCount(session('member_id'));
        $this->assign('home_member_info', $member_info);

        return $this->fetch($this->template_dir . 'member_info');
    }

    public function ajax_load_order_info() {
        $order_model = model('order');

        //交易提醒 - 显示数量
        $member_info['order_nopay_count'] = $order_model->getOrderCountByID(session('member_id'), 'NewCount');
        $member_info['order_noreceipt_count'] = $order_model->getOrderCountByID(session('member_id'), 'SendCount');
        $member_info['order_noeval_count'] = $order_model->getOrderCountByID(session('member_id'), 'EvalCount');
        $this->assign('home_member_info', $member_info);

        //交易提醒 - 显示订单列表
        $condition = array();
        $condition['buyer_id'] = session('member_id');
        $condition['order_state'] = array('in', array(ORDER_STATE_NEW, ORDER_STATE_PAY, ORDER_STATE_SEND, ORDER_STATE_SUCCESS));
        $order_list = $order_model->getNormalOrderList($condition, '', '*', 'order_id desc', 3, array('order_goods'));

        foreach ($order_list as $order_id => $order) {
            //显示物流跟踪
            $order_list[$order_id]['if_deliver'] = $order_model->getOrderOperateState('deliver', $order);
            //显示评价
            $order_list[$order_id]['if_evaluation'] = $order_model->getOrderOperateState('evaluation', $order);
            //显示支付
            $order_list[$order_id]['if_payment'] = $order_model->getOrderOperateState('payment', $order);
            //显示收货
            $order_list[$order_id]['if_receive'] = $order_model->getOrderOperateState('receive', $order);
        }

        $this->assign('order_list', $order_list);

        //取出购物车信息
        $cart_model = model('cart');
        $cart_list = $cart_model->getCartList('db', array('buyer_id' => session('member_id')), 3);
        $this->assign('cart_list', $cart_list);
        return $this->fetch($this->template_dir . 'order_info');
    }
    public function ajax_load_point_info(){
                //开启代金券功能后查询推荐的热门代金券列表
        if (config('voucher_allow') == 1){
            $recommend_voucher = model('voucher')->getRecommendTemplate(2);
            $this->assign('recommend_voucher',$recommend_voucher);
        }
        //开启积分兑换功能后查询推荐的热门兑换商品列表
        if (config('pointprod_isuse') == 1){
            //热门积分兑换商品
            $recommend_pointsprod = model('pointprod')->getRecommendPointProd(2);
            $this->assign('recommend_pointsprod',$recommend_pointsprod);
        }
        return $this->fetch($this->template_dir . 'point_info');
    }
    public function ajax_load_goods_info() {
        //商品收藏
        $favorites_model = model('favorites');
        $favorites_list = $favorites_model->getGoodsFavoritesList(array('member_id' => session('member_id')), '*', 7);
        if (!empty($favorites_list) && is_array($favorites_list)) {
            $favorites_id = array(); //收藏的商品编号
            foreach ($favorites_list as $key => $favorites) {
                $fav_id = $favorites['fav_id'];
                $favorites_id[] = $favorites['fav_id'];
                $favorites_key[$fav_id] = $key;
            }
            $goods_model = model('goods');
            $field = 'goods.goods_id,goods.goods_name,goods.goods_image,goods.goods_price,goods.evaluation_count,goods.goods_salenum,goods.goods_collect';
            $goods_list = $goods_model->getGoodsList(array('goods_id' => array('in', $favorites_id)), $field);
            if (!empty($goods_list) && is_array($goods_list)) {
                foreach ($goods_list as $key => $fav) {
                    $fav_id = $fav['goods_id'];
                    $fav['goods_member_id'] = $fav['member_id'];
                    $key = $favorites_key[$fav_id];
                    $favorites_list[$key]['goods'] = $fav;
                }
            }
        }
        $this->assign('favorites_list', $favorites_list);

        $goods_count_new = array();
        if (!empty($favorites_id)) {
            foreach ($favorites_id as $v) {
                $count = model('goods')->getGoodsCommonOnlineCount();
                $goods_count_new[$v] = $count;
            }
        }
        $this->assign('goods_count', $goods_count_new);
        return $this->fetch($this->template_dir . 'goods_info');
    }

}

?>
