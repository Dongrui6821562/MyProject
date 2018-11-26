<?php
/**
 * 运单模板
 */

namespace app\admin\controller;


class Waybill extends AdminControl
{
    private $url_waybill_list;
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
         $this->url_waybill_list=url('Waybill/index');
    }

    /**
     * 运单模板列表
     */
    public function index()
    {
        $waybill_model = model('waybill');

        $waybill_list = $waybill_model->getWaybillAdminList(10);
        $this->assign('waybill_list', $waybill_list);
        $this->assign('show_page', $waybill_model->page_info->render());

        $this->setAdminCurItem('waybill_list');
        return $this->fetch();
    }

    /**
     * 添加运单模板
     */
    public function waybill_add()
    {
        $express_model = model('express');
        $this->assign('express_list', $express_model->getExpressList());
        
        $waybill_info = array(
            'waybill_usable'=>1,
        );
        $this->assign('waybill_info', $waybill_info);
        $this->setAdminCurItem('waybill_add');
        return $this->fetch();
    }

    /**
     * 保存运单模板
     */
    public function waybill_save()
    {
        $waybill_model = model('waybill');
        $result = $waybill_model->saveWaybill($_POST);

        if (!isset($result['error'])) {
            $this->log('保存运单模板' . '[ID:' . $result . ']', 1);
            $this->success(lang('ds_common_save_succ'), $this->url_waybill_list);
        }
        else {
            $this->error(lang('ds_common_save_fail'), $this->url_waybill_list);
        }
    }

    /**
     * 编辑运单模板
     */
    public function waybill_edit()
    {
        $express_model = model('express');
        $waybill_model = model('waybill');

        $waybill_info = $waybill_model->getWaybillInfoByID(input('param.waybill_id'));
        if (!$waybill_info) {
            $this->error('运单模板不存在');
        }
        $this->assign('waybill_info', $waybill_info);

        $express_list = $express_model->getExpressList();
        foreach ($express_list as $key => $value) {
            if ($value['express_id'] == $waybill_info['express_id']) {
                $express_list[$key]['selected'] = true;
            }
        }
        $this->assign('express_list', $express_list);

        $this->setAdminCurItem('waybill_edit');
        return $this->fetch('waybill_add');
    }

    /**
     * 设计运单模板
     */
    public function waybill_design()
    {
        $waybill_model = model('waybill');

        $result = $waybill_model->getWaybillDesignInfo(input('param.waybill_id'));
        if (isset($result['error'])) {
            $this->error($result['error']);
        }

        $this->assign('waybill_info', $result['waybill_info']);
        $this->assign('waybill_info_data', $result['waybill_info_data']);
        $this->assign('waybill_item_list', $result['waybill_item_list']);
        $this->setAdminCurItem('waybill_design');
        return $this->fetch();
    }

    /**
     * 设计运单模板保存
     */
    public function waybill_design_save()
    {
        $waybill_model = model('waybill');

        $result = $waybill_model->editWaybillDataByID($_POST['waybill_data'], input('post.waybill_id'));

        if ($result) {
            $this->log('保存运单模板设计' . '[ID:' . input('post.waybill_id') . ']', 1);
            $this->success(lang('ds_common_save_succ'), $this->url_waybill_list);
        }
        else {
            $this->log('保存运单模板设计' . '[ID:' . input('post.waybill_id') . ']', 0);
            $this->error(lang('ds_common_save_fail'), $this->url_waybill_list);
        }
    }

    /**
     * 删除运单模板
     */
    public function waybill_del()
    {
        $waybill_id = intval(input('param.waybill_id'));
        if ($waybill_id <= 0) {
            ds_json_encode(10001, lang('param_error'));
        }

        $waybill_model = model('waybill');

        $result = $waybill_model->delWaybill(array('waybill_id' => $waybill_id));
        if ($result) {
            $this->log('删除运单模板' . '[ID:' . $waybill_id . ']', 1);
            ds_json_encode(10000, lang('ds_common_del_succ'));
        }
        else {
            $this->log('删除运单模板' . '[ID:' . $waybill_id . ']', 0);
            ds_json_encode(10001, lang('ds_common_del_fail'));
        }
    }

    /**
     * 打印测试
     */
    public function waybill_test()
    {
        $waybill_model = model('waybill');

        $waybill_info = $waybill_model->getWaybillInfoByID(input('param.waybill_id'));
        if (!$waybill_info) {
            $this->error('运单模板不存在');
        }
        $this->assign('waybill_info', $waybill_info);
        return $this->fetch();
    }

    /**
     * ajax操作
     */
    public function ajax()
    {
        switch (input('param.branch')) {
            case 'usable':
                $waybill_model = model('waybill');
                $where = array('waybill_id' => intval(input('param.id')));
                $update = array('waybill_usable' => intval(input('param.value')));
                $waybill_model->editWaybill($update, $where);
                echo 'true';
                exit;
                break;
        }
    }

    /**
     * 设置默认打印模板
     */
    public function waybill_set_default() {
        $waybill_id = intval(input('param.waybill_id'));

        $waybill_model = model('waybill');

        $result = $waybill_model->editwaybillDefault($waybill_id);

        if($result) {
            dsLayerOpenSuccess('默认模板设置成功');
        } else {
            $this->error('默认模板设置失败');
        }
    }

    /**
     * 页面内导航菜单
     * @param string $menu_key 当前导航的menu_key
     * @param array $array 附加菜单
     * @return
     */
    protected function getAdminItemList()
    {
        $menu_array = array(
            array(
                'name' => 'waybill_list', 'text' => '列表', 'url' => url('Waybill/index')
            ),
            array(
                'name' => 'waybill_add', 'text' => '添加', 'url' => url('Waybill/waybill_add')
            ),
        );
        if (request()->action() == 'waybill_edit') {
            $menu_array[] = array('name' => 'waybill_edit', 'text' => '编辑', 'url' => 'javascript:;');
        }
        if (request()->action() == 'waybill_design') {
            $menu_array[] = array('name' => 'waybill_design', 'text' => '设计', 'url' => 'javascript:;');
        }
        return $menu_array;
    }
}