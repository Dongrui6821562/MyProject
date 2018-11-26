<?php

namespace app\home\controller;

use think\Lang;
use think\Validate;

class Memberaddress extends BaseMember {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/'.config('default_lang').'/memberaddress.lang.php');
    }

    /*
     * 收货地址列表
     */

    public function index() {
        $address_model=model('address');
        $address_list = $address_model->getAddressList(array('member_id'=>session('member_id')));
        $this->assign('address_list', $address_list);

        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('member_address');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('my_address');
        return $this->fetch($this->template_dir . 'index');
    }

    public function add() {
        if (!request()->isPost()) {
            $area_mod=model('area');
            $region_list = $area_mod->getAreaList(array('area_parent_id'=>'0'));
            $this->assign('region_list', $region_list);
            $address = array(
                'address_realname' => '',
                'area_id' => '',
                'city_id' => '',
                'address_detail' => '',
                'address_tel_phone' => '',
                'address_mob_phone' => '',
                'address_is_default' => '',
                'area_info' => '',
                'address_longitude' => '',
                'address_latitude' => '',
            );
            $this->assign('address', $address);
            /* 设置买家当前菜单 */
            $this->setMemberCurMenu('member_address');
            /* 设置买家当前栏目 */
            $this->setMemberCurItem('my_address_add');
            $this->assign('baidu_ak', config('baidu_ak'));
            return $this->fetch($this->template_dir . 'form');
        } else {
            $data = array(
                'member_id' => session('member_id'),
                'address_realname' => input('post.true_name'),
                'area_id' => input('post.area_id'),
                'city_id' => input('post.city_id'),
                'address_detail' => input('post.address'),
                'address_longitude' => input('post.longitude'),
                'address_latitude' => input('post.latitude'),
                'address_tel_phone' => input('post.tel_phone'),
                'address_mob_phone' => input('post.mob_phone'),
                'address_is_default' => input('post.is_default') == 1 ? 1 : 0,
                'area_info' => input('post.area_info'),
            );
            //验证数据  BEGIN
            $rule = [
                ['address_realname', 'require', lang('real_names_required')],
                ['city_id', 'gt:1', lang('please_select_region')],
                ['area_id', 'gt:1', lang('region_least_two_levels')],
            ];
            $validate = new Validate();
            $validate_result = $validate->check($data, $rule);
            if (!$validate_result) {
                $this->error($validate->getError());
            }
            //验证数据  END
            $address_model=model('address');
            $result = $address_model->addAddress($data);
            if ($result) {
                $this->success(lang('ds_common_save_succ'), 'Memberaddress/index');
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    public function edit() {

        $address_id = intval(input('param.address_id'));
        if (0 >= $address_id) {
            $this->error(lang('param_error'));
        }
        $address_model=model('address');
        $address = $address_model->getAddressInfo(array('member_id' => session('member_id'), 'address_id' => $address_id));
        if (empty($address)) {
            $this->error(lang('address_does_not_exist'));
        }
        if (!request()->isPost()) {
            $area_mod=model('area');
            $region_list = $area_mod->getAreaList(array('area_parent_id'=>'0'));
            $this->assign('region_list', $region_list);
            $this->assign('address', $address);
            /* 设置买家当前菜单 */
            $this->setMemberCurMenu('member_address');
            /* 设置买家当前栏目 */
            $this->setMemberCurItem('my_address_edit');
            $this->assign('baidu_ak', config('baidu_ak'));
            return $this->fetch($this->template_dir . 'form');
        } else {
            $data = array(
                'address_realname' => input('post.true_name'),
                'area_id' => input('post.area_id'),
                'city_id' => input('post.city_id'),
                'address_detail' => input('post.address'),
                'address_longitude' => input('post.longitude'),
                'address_latitude' => input('post.latitude'),
                'address_tel_phone' => input('post.tel_phone'),
                'address_mob_phone' => input('post.mob_phone'),
                'address_is_default' => input('post.is_default') == 1 ? 1 : 0,
                'area_info' => input('post.area_info'),
            );
            //验证数据  BEGIN
            $rule = [
                ['address_realname', 'require', lang('real_names_required')],
                ['city_id', 'gt:1', lang('please_select_region')],
                ['area_id', 'gt:1', lang('region_least_two_levels')],
            ];
            $validate = new Validate();
            $validate_result = $validate->check($data, $rule);
            if (!$validate_result) {
                $this->error($validate->getError());
            }
            //验证数据  END

            $result = $address_model->editAddress($data,array('member_id' => session('member_id'), 'address_id' => $address_id));
            if ($result) {
                $this->success(lang('ds_common_save_succ'), 'Memberaddress/index');
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    public function drop() {
        $address_id = intval(input('param.address_id'));
        if (0 >= $address_id) {
            $this->error(lang('empty_error'));
        }
        $address_model=model('address');
        $result = $address_model->delAddress(array('address_id'=>$address_id));
        if ($result) {
            $this->success(lang('ds_common_del_succ'), 'Memberaddress/index');
        } else {
            $this->error(lang('ds_common_del_fail'));
        }
    }


    /**
     *    栏目菜单
     */
    function getMemberItemList() {
        $item_list = array(
            array(
                'name' => 'my_address',
                'text' => lang('my_address'),
                'url' => url('Memberaddress/index'),
            ),
            array(
                'name' => 'my_address_add',
                'text' => lang('new_address'),
                'url' => url('Memberaddress/add'),
            ),
        );
        if (request()->action() == 'edit') {
            $item_list[] = array(
                'name' => 'my_address_edit',
                'text' => lang('edit_address'),
                'url' => "javascript:void(0)",
            );
        }
        return $item_list;
    }

}

?>
