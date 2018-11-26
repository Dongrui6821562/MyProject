<?php

namespace app\admin\controller;

use think\Lang;
use think\Validate;

class Voucher extends AdminControl {

    private $templatestate_arr;

    public function _initialize() {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH . 'admin/lang/' . config('default_lang') . '/voucher.lang.php');
        if (config('voucher_allow') != 1 || config('points_isuse') != 1) {
            $this->error(lang('admin_voucher_unavailable'), 'operation/setting');
        }
        //代金券模板状态
        $this->templatestate_arr = array(
            'usable' => array(1, lang('admin_voucher_templatestate_usable')),
            'disabled' => array(2, lang('ds_invalidation'))
        );
        $this->assign('templatestate_arr', $this->templatestate_arr);
    }

    /**
     * 代金券列表
     */
    public function index() {
        //代金券设置为失效
        $this->check_voucher_template_expire();
        
        $param = array();
        if (trim(input('param.sdate')) && trim(input('param.edate'))) {
            $sdate = strtotime(input('param.sdate'));
            $edate = strtotime(input('param.edate'));
            $param['vouchertemplate_adddate'] = array('between', "$sdate,$edate");
        } elseif (trim(input('param.sdate'))) {
            $sdate = strtotime(input('param.sdate'));
            $param['vouchertemplate_adddate'] = array('egt', $sdate);
        } elseif (trim(input('param.edate'))) {
            $edate = strtotime(input('param.edate'));
            $param['vouchertemplate_adddate'] = array('elt', $edate);
        }
        $state = intval(input('param.state'));
        if ($state) {
            $param['vouchertemplate_state'] = $state;
        }
        if (input('param.recommend') === '1') {
            $param['vouchertemplate_recommend'] = 1;
        } elseif (input('param.recommend') === '0') {
            $param['vouchertemplate_recommend'] = 0;
        }
        $voucher_model = model('voucher');
        $vouchertemplate_list = $voucher_model->getVouchertemplateList($param, '', '', 10, 'vouchertemplate_state asc,vouchertemplate_id desc');
        
        foreach ($vouchertemplate_list as $key => $val) {
            if (!$val['vouchertemplate_customimg'] || !file_exists(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS . $val['vouchertemplate_customimg'])) {
                $vouchertemplate_list[$key]['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . default_goodsimage(60);
            } else {
                $vouchertemplate_list[$key]['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . ATTACH_VOUCHER . DS . $val['vouchertemplate_customimg'];
            }
        }

        $this->assign('show_page', $voucher_model->page_info->render());

        $this->assign('vouchertemplate_list', $vouchertemplate_list);
        $this->setAdminCurItem('index');
        return $this->fetch();
    }

    /*
     * 代金券模版添加
     */

    public function templateadd() {
        $voucher_model = model('voucher');

        if (request()->isPost()) {
            //验证提交的内容面额不能大于限额
            $obj_validate = new Validate();
            $data = [
                'vouchertemplate_title' => input('post.vouchertemplate_title'),
                'vouchertemplate_total' => input('post.vouchertemplate_total'),
                'vouchertemplate_price' => input('post.vouchertemplate_price'),
                'vouchertemplate_limit' => input('post.vouchertemplate_limit'),
                'vouchertemplate_desc' => input('post.vouchertemplate_desc'),
            ];

            $rule = [
                    ['vouchertemplate_title', 'require|length:1,50', lang('voucher_template_title_error')],
                    ['vouchertemplate_total', 'require|number', lang('voucher_template_total_error')],
                    ['vouchertemplate_price', 'require|number', lang('voucher_template_price_error')],
                    ['vouchertemplate_limit', 'require', lang('voucher_template_limit_error')],
                    ['vouchertemplate_desc', 'require|length:1,255', lang('voucher_template_describe_error')]
            ];

            $res = $obj_validate->check($data, $rule);
            $error = '';
            if (!$res) {
                $error .= $obj_validate->getError();
            }
            
            //金额验证
            $price = intval(input('post.vouchertemplate_price')) > 0 ? intval(input('post.vouchertemplate_price')) : 0;
            $limit = intval(input('post.vouchertemplate_limit')) > 0 ? intval(input('post.vouchertemplate_limit')) : 0;
            if ($price >= $limit)
                $error .= lang('voucher_template_price_error');
            if ($error) {
                $this->error($error);
            } else {
                $insert_arr = array();
                $insert_arr['vouchertemplate_title'] = trim(input('post.vouchertemplate_title'));
                $insert_arr['vouchertemplate_desc'] = trim(input('post.vouchertemplate_desc'));
                $insert_arr['vouchertemplate_startdate'] = time(); //默认代金券模板的有效期为当前时间
                if (input('post.vouchertemplate_enddate')) {
                    $enddate = strtotime(input('post.vouchertemplate_enddate'));
                    $insert_arr['vouchertemplate_enddate'] = $enddate;
                } else {//如果没有添加有效期则默认为套餐的结束时间
                    $insert_arr['vouchertemplate_enddate'] = TIMESTAMP + 30 * 3600 * 24;
                }
                $insert_arr['vouchertemplate_price'] = $price;
                $insert_arr['vouchertemplate_limit'] = $limit;
                $insert_arr['vouchertemplate_state'] = $this->templatestate_arr['usable'][0];
                $insert_arr['vouchertemplate_total'] = intval(input('post.vouchertemplate_total')) > 0 ? intval(input('post.vouchertemplate_total')) : 0;
                $insert_arr['vouchertemplate_giveout'] = 0;
                $insert_arr['vouchertemplate_used'] = 0;
                $insert_arr['vouchertemplate_gettype'] = 1;
                $insert_arr['vouchertemplate_adddate'] = TIMESTAMP;
                $insert_arr['vouchertemplate_points'] = intval(input('post.vouchertemplate_points'));
                $insert_arr['vouchertemplate_eachlimit'] = intval(input('post.eachlimit')) > 0 ? intval(input('post.eachlimit')) : 0;
                //自定义图片
                if (!empty($_FILES['customimg']['name'])) {

                    $uploaddir = BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS;
                    $file_name = date('YmdHis') . rand(10000, 99999);
                    $file_object = request()->file('customimg');
                    $info = $file_object->rule('uniqid')->validate(['ext' => ALLOW_IMG_EXT])->move($uploaddir, $file_name);
                    if ($info) {
                        $insert_arr['vouchertemplate_customimg'] = $info->getFilename();
                    }
                }
                $rs = db('vouchertemplate')->insert($insert_arr);
                if ($rs) {
                    dsLayerOpenSuccess(lang('ds_common_save_succ'));
                } else {
                    ds_show_dialog(lang('ds_common_save_fail'));
                }
            }
        } else {

            $this->assign('type', 'add');

            $t_info = array(
                'vouchertemplate_recommend'=>0,
                'vouchertemplate_enddate'=>TIMESTAMP,
                'vouchertemplate_state'=>1,
                'vouchertemplate_price'=>0,
            );
            $this->assign('t_info', $t_info);

            $this->setAdminCurItem('templateadd');
            return $this->fetch('templateedit');
        }
    }

    
    /*
     * 代金券模版编辑
     */

    public function templateedit() {
        $t_id = intval(input('param.tid'));
        if ($t_id <= 0) {
            $this->error(lang('wrong_argument'), url('Voucher/templatelist'));
        }
        //查询模板信息
        $param = array();
        $param['vouchertemplate_id'] = $t_id;
        $t_info = db('vouchertemplate')->where($param)->find();
        if (empty($t_info)) {
            $this->error(lang('wrong_argument'), 'Voucher/templatelist');
        }

        if (request()->isPost()) {
            //验证提交的内容面额不能大于限额
            $obj_validate = new Validate();
            $data = [
                'vouchertemplate_title' => input('post.vouchertemplate_title'),
                'vouchertemplate_total' => input('post.vouchertemplate_total'),
                'vouchertemplate_price' => input('post.vouchertemplate_price'),
                'vouchertemplate_limit' => input('post.vouchertemplate_limit'),
                'vouchertemplate_desc' => input('post.vouchertemplate_desc'),
            ];

            $rule = [
                    ['vouchertemplate_title', 'require|length:1,50', lang('voucher_template_title_error')],
                    ['vouchertemplate_total', 'require|number', lang('voucher_template_total_error')],
                    ['vouchertemplate_price', 'require|number', lang('voucher_template_price_error')],
                    ['vouchertemplate_limit', 'require', lang('voucher_template_limit_error')],
                    ['vouchertemplate_desc', 'require|length:1,255', lang('voucher_template_describe_error')]
            ];

            $res = $obj_validate->check($data, $rule);
            $error = '';
            if (!$res) {
                $error .= $obj_validate->getError();
            }
            //金额验证
            $price = intval(input('post.vouchertemplate_price')) > 0 ? intval(input('post.vouchertemplate_price')) : 0;
            $limit = intval(input('post.vouchertemplate_limit')) > 0 ? intval(input('post.vouchertemplate_limit')) : 0;
            if ($price >= $limit)
                $error .= lang('voucher_template_price_error');
            if ($error) {
                ds_show_dialog($error, 'reload', 'error');
            } else {
                $update_arr = array();
                $update_arr['vouchertemplate_title'] = trim(input('post.vouchertemplate_title'));
                $update_arr['vouchertemplate_desc'] = trim(input('post.vouchertemplate_desc'));
                if (input('post.vouchertemplate_enddate')) {
                    $enddate = strtotime(input('post.vouchertemplate_enddate'));
                    $update_arr['vouchertemplate_enddate'] = $enddate;
                } else {//如果没有添加有效期则默认为套餐的结束时间
                    $update_arr['vouchertemplate_enddate'] = TIMESTAMP+3600*24*30;
                }
                $update_arr['vouchertemplate_price'] = $price;
                $update_arr['vouchertemplate_limit'] = $limit;
                $update_arr['vouchertemplate_state'] = intval(input('post.tstate')) == $this->templatestate_arr['usable'][0] ? $this->templatestate_arr['usable'][0] : $this->templatestate_arr['disabled'][0];
                $update_arr['vouchertemplate_total'] = intval(input('post.vouchertemplate_total')) > 0 ? intval(input('post.vouchertemplate_total')) : 0;
                $update_arr['vouchertemplate_points'] = intval(input('post.vouchertemplate_points'));
                $update_arr['vouchertemplate_eachlimit'] = intval(input('post.eachlimit')) > 0 ? intval(input('post.eachlimit')) : 0;
                //自定义图片
                if (!empty($_FILES['customimg']['name'])) {
                    $uploaddir = BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS;
                    $file_name =  date('YmdHis') . rand(10000, 99999);
                    $file_object = request()->file('customimg');
                    $info = $file_object->validate(['ext' => ALLOW_IMG_EXT])->move($uploaddir, $file_name);
                    if ($info) {
                        //删除原图
                        if (!empty($t_info['vouchertemplate_customimg'])) {//如果模板存在，则删除原模板图片
                            @unlink(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS . $t_info['vouchertemplate_customimg']);
                        }
                        $update_arr['vouchertemplate_customimg'] = $info->getFilename();
                    }
                }

                $rs = db('vouchertemplate')->where(array('vouchertemplate_id' => $t_info['vouchertemplate_id']))->update($update_arr);
                if ($rs) {
                    ds_show_dialog(lang('ds_common_op_succ'), url('Voucher/templatelist'), 'succ');
                } else {
                    ds_show_dialog(lang('ds_common_op_fail'), url('Voucher/templatelist'), 'error');
                }
            }
        } else {
            if (!$t_info['vouchertemplate_customimg'] || !file_exists(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS . $t_info['vouchertemplate_customimg'])) {
                $t_info['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . default_goodsimage(240);
            } else {
                $t_info['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . ATTACH_VOUCHER . DS . $t_info['vouchertemplate_customimg'];
            }
            $this->assign('type', 'edit');
            $this->assign('t_info', $t_info);
            $this->setAdminCurItem('templateedit');
            return $this->fetch('templateedit');
        }
    }

    /*
     * 把代金券模版设为失效
     */

    private function check_voucher_template_expire($voucher_template_id = '') {
        $where_array = array();
        if (empty($voucher_template_id)) {
            $where_array['vouchertemplate_enddate'] = array('lt', time());
        } else {
            $where_array['vouchertemplate_id'] = $voucher_template_id;
        }
        $where_array['vouchertemplate_state'] = $this->templatestate_arr['usable'][0];
        db('vouchertemplate')->where($where_array)->update(array('vouchertemplate_state' => $this->templatestate_arr['disabled'][0]));
    }
    
    
    /**
     * 删除代金券
     */
    public function templatedel() {
        $t_id = intval(input('param.tid'));
        if ($t_id <= 0) {
            $this->error(lang('wrong_argument'), url('Voucher/templatelist'));
        }
        //查询模板信息
        $param = array();
        $param['vouchertemplate_id'] = $t_id;
        $param['vouchertemplate_giveout'] = array('elt', '0'); //会员没领取过代金券才可删除
        $t_info = db('vouchertemplate')->where($param)->find();
        if (empty($t_info)) {
            $this->error(lang('wrong_argument'), 'Voucher/index');
        }
        $rs = db('vouchertemplate')->where(array('vouchertemplate_id' => $t_info['vouchertemplate_id']))->delete();
        if ($rs) {
            //删除自定义的图片
            if (trim($t_info['vouchertemplate_customimg'])) {
                @unlink(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS . $t_info['vouchertemplate_customimg']);
            }
            ds_show_dialog(lang('ds_common_del_succ'), 'reload', 'succ');
        } else {
            ds_show_dialog(lang('ds_common_del_fail'));
        }
    }

    /**
     * ajax操作
     */
    public function ajax() {
        $voucher_model = model('voucher');
        switch (input('param.branch')) {
            case 'vouchertemplate_recommend':
                $voucher_model->editVouchertemplate(array('vouchertemplate_id' => intval(input('param.id'))), array(input('param.column') => intval(input('param.value'))));
                $logtext = '';
                if (intval(input('param.value')) == 1) {//推荐代金券
                    $logtext = '推荐代金券';
                } else {
                    $logtext = '取消推荐代金券';
                }
                $this->log($logtext . '[ID:' . intval(input('param.id')) . ']', 1);
                echo 'true';
                exit;
                break;
        }
    }

    /**
     * 页面内导航菜单
     * @param string $menu_key 当前导航的menu_key
     * @param array $array 附加菜单
     * @return
     */
    protected function getAdminItemList() {
        $menu_array = array(
            array(
                'name' => 'index',
                'text' => lang('admin_voucher_template_manage'),
                'url' => url('Voucher/index')
            ),
            array(
                'name' => 'templateadd',
                'text' => lang('admin_voucher_template_add'),
                'url' => url('Voucher/templateadd')
            ),
        );

        if (request()->action() == 'templateedit') {
            $menu_array = array(
                array(
                    'name' => 'index',
                    'text' => lang('admin_voucher_template_manage'),
                    'url' => url('Voucher/index')
                ), array(
                    'name' => 'templateedit',
                    'text' => lang('admin_voucher_template_edit'),
                    'url' => ''
                )
            );
        }
        return $menu_array;
    }

}
