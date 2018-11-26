<?php
/**
 * 推荐人设置
 */

namespace app\admin\controller;


use think\Lang;
use think\Validate;
class Inviter extends AdminControl
{
public function _initialize()
{
    parent::_initialize(); // TODO: Change the autogenerated stub
    Lang::load(APP_PATH.'admin/lang/'.config('default_lang').'/inviter.lang.php');
}

    /**
     * 基本设置
     */
    public function setting(){
        $config_model = model('config');
        if (request()->isPost()){
            $upload_file = BASE_UPLOAD_PATH . DS . ATTACH_COMMON;
            if (!empty($_FILES['inviter_back']['name'])) {
                $file = request()->file('inviter_back');
                $info = $file->validate(['ext'=>ALLOW_IMG_EXT])->move($upload_file, 'inviter_back');
                if ($info) {
                    $upload['inviter_back'] = $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }
            }
            if (!empty($upload['inviter_back'])) {
                $update_array['inviter_back'] = $upload['inviter_back'];
            }
            $update_array['inviter_ratio_1']=floatval(input('post.inviter_ratio_1'));
            $update_array['inviter_ratio_2']=floatval(input('post.inviter_ratio_2'));
            $update_array['inviter_ratio_3']=floatval(input('post.inviter_ratio_3'));
            
            $update_array['inviter_open']=intval(input('post.inviter_open'));
            $update_array['inviter_level']=intval(input('post.inviter_level'));
            $update_array['inviter_show']=floatval(input('post.inviter_show'));
            $update_array['inviter_return']=floatval(input('post.inviter_return'));
            $update_array['inviter_view']=floatval(input('post.inviter_view'));
            $update_array['inviter_condition']=floatval(input('post.inviter_condition'));
            $update_array['inviter_condition_amount']=floatval(input('post.inviter_condition_amount'));
            
            
            $result = $config_model->editConfig($update_array);
            if ($result) {
                dkcache('config');
                $this->log(lang('ds_inviter_set'),1);
                $this->success(lang('ds_common_op_succ'), 'Inviter/setting');
            }else{
                $this->log(lang('ds_inviter_set'),0);
            }
        }
        $list_setting = $config_model->getConfigList();
        $this->assign('list_setting',$list_setting);
        $this->setAdminCurItem('index');
        return $this->fetch('index');
    }
    
    public function order(){
        $search_field_value = input('param.search_field_value');
        $search_field_name = input('param.search_field_name');
        $condition = array();
        if ($search_field_value != '') {
            switch ($search_field_name) {
                case 'orderinviter_member_name':
                    $condition['orderinviter_member_name'] = $search_field_value;
                    break;
                case 'orderinviter_order_sn':
                    $condition['orderinviter_order_sn'] = $search_field_value;
                    break;
            }
        }
  
        $orderinviter_list=db('orderinviter')->where($condition)->order('orderinviter_addtime desc')->paginate(10,false,['query' => request()->param()]);
        $this->assign('orderinviter_list', $orderinviter_list->items());
        $this->assign('show_page', $orderinviter_list->render());
        $this->assign('search_field_name', trim($search_field_name));
        $this->assign('search_field_value', trim($search_field_value));
        $this->assign('filtered', $condition ? 1 : 0); //是否有查询条件
        $this->setAdminCurItem('order');
        return $this->fetch();
    }
    
    public function member(){
        $inviter_model=model('inviter');
        $search_field_value = input('search_field_value');
        $search_field_name = input('search_field_name');
        $condition = array();
        if ($search_field_value != '') {
            switch ($search_field_name) {
                case 'member_name':
                    $condition['member_name'] = array('like', '%' . trim($search_field_value) . '%');
                    break;
                case 'member_email':
                    $condition['member_email'] = array('like', '%' . trim($search_field_value) . '%');
                    break;
                case 'member_mobile':
                    $condition['member_mobile'] = array('like', '%' . trim($search_field_value) . '%');
                    break;
                case 'member_truename':
                    $condition['member_truename'] = array('like', '%' . trim($search_field_value) . '%');
                    break;
            }
        }
  
        $member_list = $inviter_model->getInviterList($condition, 10,'','i.*,m.inviter_id as inviter_parent_id,m.member_id,m.member_ww,m.member_qq,m.member_addtime,m.member_name,m.member_avatar,m.member_email,m.member_mobile,m.member_truename');
        $inviterclass_model=model('inviterclass');
        foreach($member_list as $key => $item){
            $member_list[$key]['inviter_parent_name']='';
            $member_list[$key]['inviter_class']='';
            $member_list[$key]['inviter_class']=$inviterclass_model->getInviterclass($item['inviter_total_amount']);
            if($item['inviter_parent_id']){
                $member_list[$key]['inviter_parent_name'] = db('member')->where('member_id='.$item['inviter_parent_id'])->value('member_name');
            }
        }
        $this->assign('member_list', $member_list);
        $this->assign('show_page', $inviter_model->page_info->render());
        $this->assign('search_field_name', trim($search_field_name));
        $this->assign('search_field_value', trim($search_field_value));
        $this->assign('filtered', $condition ? 1 : 0); //是否有查询条件
        $this->setAdminCurItem('member');
        return $this->fetch('member');
    }
    public function memberclass(){
        $memberclass_list=db('inviterclass')->order('inviterclass_amount asc')->select();
        $this->assign('memberclass_list',$memberclass_list);
        $this->setAdminCurItem('memberclass');
        return $this->fetch('memberclass');
    }
    
    public function member_adjust(){
        $member_id=intval(input('param.member_id'));
        if(!$member_id){
            $this->error(lang('param_error'));
        }
        $inviter_model=model('inviter');
        $inviter_info=$inviter_model->getInviterInfo(array('m.member_id'=>$member_id),'m.member_id,m.inviter_id as inviter_parent_id,i.inviter_1_quantity,i.inviter_2_quantity,i.inviter_3_quantity');
        if(!$inviter_info){
            $this->error(lang('inviter_member_empty'));
        }
        if (request()->isPost()) {
            $member_name=trim(input('param.member_name'));
            if($member_name){
                $inviter=$inviter_model->getInviterInfo(array('m.member_name'=>$member_name),'m.member_id');
                $inviter_id=$inviter['member_id'];
                if(!$inviter_id){
                    $this->error(lang('inviter_member_empty'));
                }
                //上级不能是自己
                if($inviter_id==$member_id){
                    $this->error(lang('inviter_parent_error'));
                }
                //上级不能是自己下级中（3级内）的成员
                if(db('member')->where('inviter_id='.$member_id.' AND member_id='.$inviter_id)->value('member_id')){
                    $this->error(lang('inviter_parent_error2'));
                }
                $subQuery=db('member')->field('member_id')->where('inviter_id='.$member_id)->buildSql();
                
                if(db('member')->where('member_id='.$inviter_id.' AND inviter_id IN'.$subQuery)->value('member_id')){
                    $this->error(lang('inviter_parent_error2'));
                }
                $subQuery=db('member')->field('member_id')->where('inviter_id IN'.$subQuery)->buildSql();
                if(db('member')->where('member_id='.$inviter_id.' AND inviter_id IN'.$subQuery)->value('member_id')){
                    $this->error(lang('inviter_parent_error2'));
                }
            }else{
                $inviter_id=0;
            }
            db('member')->where('member_id='.$member_id)->update(array(
                'inviter_id'=>$inviter_id
            ));
            //给旧的父级减去下线成员
            if($inviter_info['inviter_parent_id']){
                db('inviter')->where('inviter_id='.$inviter_info['inviter_parent_id'].' AND inviter_1_quantity>=1')->setDec('inviter_1_quantity');
                db('inviter')->where('inviter_id='.$inviter_info['inviter_parent_id'].' AND inviter_2_quantity>='.$inviter_info['inviter_1_quantity'])->setDec('inviter_2_quantity',$inviter_info['inviter_1_quantity']);
                db('inviter')->where('inviter_id='.$inviter_info['inviter_parent_id'].' AND inviter_3_quantity>='.$inviter_info['inviter_2_quantity'])->setDec('inviter_3_quantity',$inviter_info['inviter_2_quantity']);
                //父级的父级
                $temp=$inviter_model->getInviterInfo(array('m.member_id'=>$inviter_info['inviter_parent_id']),'m.inviter_id as inviter_parent_id');
                if($temp){
                    db('inviter')->where('inviter_id='.$temp['inviter_parent_id'].' AND inviter_2_quantity>=1')->setDec('inviter_2_quantity');
                    db('inviter')->where('inviter_id='.$temp['inviter_parent_id'].' AND inviter_3_quantity>='.$inviter_info['inviter_1_quantity'])->setDec('inviter_3_quantity',$inviter_info['inviter_1_quantity']);
                    //父级的父级的父级
                    $temp=$inviter_model->getInviterInfo(array('m.member_id'=>$temp['inviter_parent_id']),'m.inviter_id as inviter_parent_id');
                    if($temp){
                        db('inviter')->where('inviter_id='.$temp['inviter_parent_id'].' AND inviter_3_quantity>=1')->setDec('inviter_3_quantity');
                    }
                }
            }
            //给新的父级增加下线成员
            if($inviter_id){
                db('inviter')->where('inviter_id='.$inviter_id)->setInc('inviter_1_quantity');
                db('inviter')->where('inviter_id='.$inviter_id)->setInc('inviter_2_quantity',$inviter_info['inviter_1_quantity']);
                db('inviter')->where('inviter_id='.$inviter_id)->setInc('inviter_3_quantity',$inviter_info['inviter_2_quantity']);
                //父级的父级
                $temp=$inviter_model->getInviterInfo(array('m.member_id'=>$inviter_id),'m.inviter_id as inviter_parent_id');
                if($temp){
                    db('inviter')->where('inviter_id='.$temp['inviter_parent_id'])->setInc('inviter_2_quantity');
                    db('inviter')->where('inviter_id='.$temp['inviter_parent_id'])->setInc('inviter_3_quantity',$inviter_info['inviter_1_quantity']);
                    //父级的父级的父级
                    $temp=$inviter_model->getInviterInfo(array('m.member_id'=>$temp['inviter_parent_id']),'m.inviter_id as inviter_parent_id');
                    if($temp){
                        db('inviter')->where('inviter_id='.$temp['inviter_parent_id'])->setInc('inviter_3_quantity');
                    }
                }
            }
            $this->log(lang('adjust_superior') .  '[ID:' . $member_id . ']', 1);
            dsLayerOpenSuccess(lang('ds_common_op_succ'));
        }else{
            return $this->fetch();
        }
    }
    
    /**
     * 添加标签
     */
    public function memberclass_add(){
        if (request()->isPost()) {
            $data=array(
                'inviterclass_name'=>trim(input('post.inviterclass_name')),
                'inviterclass_amount'=>abs(floatval(input('post.inviterclass_amount'))),
            );
            if(!$data['inviterclass_name']){
                $this->error(lang('param_error'));
            }
            db('inviterclass')->insert($data);
            dsLayerOpenSuccess(lang('ds_common_op_succ'));
        }else{
            return $this->fetch('memberclass_form');
        }
    }

    /**
     * 编辑标签
     */
    public function memberclass_edit()
    {
        $id=intval(input('param.id'));
        if(!$id){
            $this->error(lang('param_error'));
        }
        $inviterclass_info=db('inviterclass')->where('inviterclass_id',$id)->find();
        if(!$inviterclass_info){
            $this->error(lang('inviterclass_empty'));
        }
        // 实例化模型
        if (request()->isPost()) {
            $data=array(
                'inviterclass_name'=>trim(input('post.inviterclass_name')),
                'inviterclass_amount'=>abs(floatval(input('post.inviterclass_amount'))),
            );
            if(!$data['inviterclass_name'] || !$data['inviterclass_amount']){
                $this->error(lang('param_error'));
            }
            db('inviterclass')->where('inviterclass_id',$id)->update($data);
            dsLayerOpenSuccess(lang('ds_common_op_succ'));
        }  else {

            $this->assign('inviterclass_info', $inviterclass_info);
            return $this->fetch('memberclass_form');
        }
    }

    /**
     * 删除标签
     */
    public function memberclass_del()
    {
        $inviterclass_id = input('param.id');
        $inviterclass_id_array = ds_delete_param($inviterclass_id);
        if ($inviterclass_id_array == FALSE) {
            ds_json_encode('10001', lang('param_error'));
        }
 
        $result=db('inviterclass')->where(array('inviterclass_id'=>array('in',$inviterclass_id_array)))->delete();
        if ($result) {
            ds_json_encode('10000', lang('ds_common_del_succ'));
        }
        else {
            ds_json_encode('10001', lang('ds_common_del_fail'));
        }
    }

    
    
    public function memberstate(){
        $member_id=input('param.member_id');
        $member_id_array = ds_delete_param($member_id);
        $member_state=input('param.member_state');
        if(!$member_id_array || !in_array($member_state, array(1,2))){
            ds_json_encode('10001', lang('param_error'));
        }
        $inviter_model=model('inviter');
        $inviter_info=$inviter_model->getInviterInfo(array('i.inviter_id'=>array('in',$member_id_array)));
        if(!$inviter_info){
            ds_json_encode('10001', lang('inviter_member_empty'));
        }
        $inviter_model->editInviter(array('inviter_id'=>array('in',$member_id_array)),array('inviter_state'=>$member_state));
        $this->log(($member_state==1?lang('ds_enable'):lang('ds_disable')) .  '[ID:' . implode(',', $member_id_array) . ']', 1);
        ds_json_encode('10000', ($member_state==1?lang('ds_enable'):lang('ds_disable')).lang('ds_succ'));
    }

    public function memberinfo(){
        $member_id=input('param.member_id');
        if(!$member_id){
            ds_json_encode('10001', lang('param_error'));
        }
        $inviter_model=model('inviter');
        $inviter_info=$inviter_model->getInviterInfo(array('i.inviter_id'=>$member_id),'i.*,m.inviter_id as inviter_parent_id,m.member_id,m.member_ww,m.member_qq,m.member_addtime,m.member_name,m.member_avatar,m.member_email,m.member_mobile,m.member_truename');
        if(!$inviter_info){
            ds_json_encode('10001', lang('inviter_member_empty'));
        }
        $inviter_info['inviter_parent_name']='';
        if($inviter_info['inviter_parent_id']){
            $inviter_info['inviter_parent_name']= db('member')->where('member_id='.$inviter_info['inviter_parent_id'])->value('member_name');
        }
        $inviterclass_model=model('inviterclass');
        $inviter_info['inviter_class']=$inviterclass_model->getInviterclass($inviter_info['inviter_total_amount']);
        $this->assign('inviter_info',$inviter_info);
        $this->setAdminCurItem('member');
        return $this->fetch('memberinfo');
    }
    
    public function memberlist(){
        $member_id=input('param.member_id');
        $type=input('param.type');
        if(!$member_id || !in_array($type, array(1,2,3))){
            return;
        }
        $inviter_model=model('inviter');
        if($type==1){
            $res=db('member')->alias('m')->join('__INVITER__ i', 'i.inviter_id=m.member_id','LEFT')->field('i.*,m.inviter_id as inviter_parent_id,m.member_id,m.member_ww,m.member_qq,m.member_addtime,m.member_name,m.member_avatar,m.member_email,m.member_mobile,m.member_truename')->where('m.inviter_id='.$member_id)->order('inviter_applytime desc')->paginate(10,false,['query' => request()->param()]);
            $page_info=$res;
            $member_list=$res->items();
        }elseif($type==2){
    
            $subQuery=db('member')->alias('m')->join('__INVITER__ i', 'i.inviter_id=m.member_id','LEFT')->field('m.member_id')->where('m.inviter_id='.$member_id)->order('inviter_applytime desc')->buildSql();
            $res=db('member')->alias('m')->join('__INVITER__ i', 'i.inviter_id=m.member_id','LEFT')->field('i.*,m.inviter_id as inviter_parent_id,m.member_id,m.member_ww,m.member_qq,m.member_addtime,m.member_name,m.member_avatar,m.member_email,m.member_mobile,m.member_truename')->where('m.inviter_id IN'.$subQuery)->order('inviter_applytime desc')->paginate(10,false,['query' => request()->param()]);
            $page_info=$res;
            $member_list=$res->items();
        }elseif($type==3){
            $subQuery=db('member')->alias('m')->join('__INVITER__ i', 'i.inviter_id=m.member_id','LEFT')->field('m.member_id')->where('m.inviter_id='.$member_id)->order('inviter_applytime desc')->buildSql();
            $subQuery=db('member')->alias('m')->join('__INVITER__ i', 'i.inviter_id=m.member_id','LEFT')->field('m.member_id')->where('m.inviter_id IN'.$subQuery)->order('inviter_applytime desc')->buildSql();
            $res=db('member')->alias('m')->join('__INVITER__ i', 'i.inviter_id=m.member_id','LEFT')->field('i.*,m.inviter_id as inviter_parent_id,m.member_id,m.member_ww,m.member_qq,m.member_addtime,m.member_name,m.member_avatar,m.member_email,m.member_mobile,m.member_truename')->where('m.inviter_id IN'.$subQuery)->order('inviter_applytime desc')->paginate(10,false,['query' => request()->param()]);
            $page_info=$res;
            $member_list=$res->items();
        }
        $inviterclass_model=model('inviterclass');
        foreach($member_list as $key => $item){
            $member_list[$key]['inviter_parent_name']='';
            $member_list[$key]['inviter_class']='';
            $member_list[$key]['inviter_class']=$inviterclass_model->getInviterclass(floatval($item['inviter_total_amount']));
            if($item['inviter_parent_id']){
                $member_list[$key]['inviter_parent_name'] = db('member')->where('member_id='.$item['inviter_parent_id'])->value('member_name');
            }
        }
        $this->assign('member_list',$member_list);
        $this->assign('show_page', $page_info->render());
        echo $this->fetch('memberlist');
    }
    
        
    public function goods(){
        $goods_model=model('goods');
        $condition = array();
        $condition['inviter_open'] = 1;
        if ((input('param.goods_name'))) {
            $condition['goods_name'] = array('like', '%' . input('param.goods_name') . '%');
        }
  
        $goods_list = $goods_model->getGoodsCommonList($condition, '*', 10);
        $this->assign('goods_list', $goods_list);
        $this->assign('show_page', $goods_model->page_info->render());
        
        $this->setAdminCurItem('goods');
        return $this->fetch('goods');
    }
    

    /**
     * 添加分销活动
     * */
    public function goods_add() {
        $goods_model=model('goods');
        if (!request()->isPost()) {
            $this->assign('config_inviter_ratio_1',config('inviter_ratio_1'));
            $this->assign('config_inviter_ratio_2',config('inviter_ratio_2'));
            $this->assign('config_inviter_ratio_3',config('inviter_ratio_3'));
            $this->setAdminCurItem('goods_add');
            return $this->fetch('goods_add');
        } else {
            //验证输入
            $inviter_goods_commonid = intval(input('post.inviter_goods_commonid'));
            $inviter_ratio_1 = floatval(input('post.inviter_ratio_1'));
            $inviter_ratio_2 = floatval(input('post.inviter_ratio_2'));
            $inviter_ratio_3 = floatval(input('post.inviter_ratio_3'));
     
            if (!($inviter_goods_commonid)) {
                ds_show_dialog(lang('inviter_goods_commonid_required'));
            }
            $goods_info=$goods_model->getGoodeCommonInfo('goods_commonid='.$inviter_goods_commonid);
            if(!$goods_info){
                ds_show_dialog(lang('sellerinviter_goods_empty'));
            }
            if ($inviter_ratio_1 > config('inviter_ratio_1')) {
                ds_show_dialog(lang('inviter_ratio_1_max').ds_percent.lang('ds_percent'));
            }
            if ($inviter_ratio_2 > config('inviter_ratio_2')) {
                ds_show_dialog(lang('inviter_ratio_2_max').ds_percent.lang('ds_percent'));
            }
            if ($inviter_ratio_3 > config('inviter_ratio_3')) {
                ds_show_dialog(lang('inviter_ratio_3_max').ds_percent.lang('ds_percent'));
            }
            $result=$goods_model->editGoodsCommonById(array(
                'inviter_open'=>1,
                'inviter_ratio_1'=>$inviter_ratio_1,
                'inviter_ratio_2'=>$inviter_ratio_2,
                'inviter_ratio_3'=>$inviter_ratio_3,
            ),array($inviter_goods_commonid));
            if ($result) {
                $this->log('添加分销商品，商品编号：' . $inviter_goods_commonid);
                dsLayerOpenSuccess(lang('goods_add_success'));
            } else {
                ds_show_dialog(lang('goods_add_fail'));
            }
        }
    }

    /**
     * 编辑分销活动
     * */
    public function goods_edit() {
        $goods_model=model('goods');
        if (!request()->isPost()) {
            $goods_commonid=intval(input('param.goods_commonid'));
            $goods_info=$goods_model->getGoodeCommonInfo('goods_commonid='.$goods_commonid.' AND inviter_open=1');
            if(!$goods_info){
                $this->error(lang('sellerinviter_goods_empty'), 'Inviter/goods_list');
            }
            $this->assign('goods_info',$goods_info);
            $this->assign('config_inviter_ratio_1',config('inviter_ratio_1'));
            $this->assign('config_inviter_ratio_2',config('inviter_ratio_2'));
            $this->assign('config_inviter_ratio_3',config('inviter_ratio_3'));
            $this->setAdminCurItem('goods_add');
            return $this->fetch('goods_add');
        } else {
            //验证输入
            $inviter_goods_commonid = intval(input('post.inviter_goods_commonid'));
            $inviter_ratio_1 = floatval(input('post.inviter_ratio_1'));
            $inviter_ratio_2 = floatval(input('post.inviter_ratio_2'));
            $inviter_ratio_3 = floatval(input('post.inviter_ratio_3'));

            if (!($inviter_goods_commonid)) {
                ds_show_dialog(lang('inviter_goods_commonid_required'));
            }
            $goods_info=$goods_model->getGoodeCommonInfo('goods_commonid='.$inviter_goods_commonid.' AND inviter_open=1');
            if(!$goods_info){
                ds_show_dialog(lang('sellerinviter_goods_empty'));
            }
            if ($inviter_ratio_1 > config('inviter_ratio_1')) {
                ds_show_dialog(lang('inviter_ratio_1_max').ds_percent.lang('ds_percent'));
            }
            if ($inviter_ratio_2 > config('inviter_ratio_2')) {
                ds_show_dialog(lang('inviter_ratio_2_max').ds_percent.lang('ds_percent'));
            }
            if ($inviter_ratio_3 > config('inviter_ratio_3')) {
                ds_show_dialog(lang('inviter_ratio_3_max').ds_percent.lang('ds_percent'));
            }
            $result=$goods_model->editGoodsCommonById(array(
                'inviter_ratio_1'=>$inviter_ratio_1,
                'inviter_ratio_2'=>$inviter_ratio_2,
                'inviter_ratio_3'=>$inviter_ratio_3,
            ),array($inviter_goods_commonid));
            if ($result) {
                $this->log('编辑分销商品，商品编号：' . $inviter_goods_commonid);
                dsLayerOpenSuccess(lang('goods_edit_success'));
            } else {
                ds_show_dialog(lang('goods_edit_fail'));
            }
        }
    }
    public function goods_del() {
        $goods_model = model('goods');
        $goods_commonid = intval(input('param.goods_commonid'));
        $goods_info = $goods_model->getGoodeCommonInfo('goods_commonid=' . $goods_commonid . ' AND inviter_open=1');
        if (!$goods_info) {
            ds_show_dialog(lang('sellerinviter_goods_empty'));
        }
        $result = $goods_model->editGoodsCommonById(array(
            'inviter_open' => 0,
                ), array($goods_commonid));
        if ($result) {
            $this->log('删除分销商品，商品编号：' . $goods_commonid);
            dsLayerOpenSuccess(lang('goods_del_success'));
        } else {
            ds_show_dialog(lang('goods_del_fail'));
        }
    }

    /**
     * 选择活动商品
     * */
    public function search_goods() {
        $goods_model = model('goods');
        $condition = array();
        $condition['goods_name'] = array('like', '%' . input('param.goods_name') . '%');
        $goods_list = $goods_model->getGoodsCommonList($condition, '*', 8);
        $this->assign('goods_list', $goods_list);
        $this->assign('show_page', $goods_model->page_info->render());
        echo $this->fetch();
        exit;
    }

    public function inviter_goods_info() {
        $goods_commonid = intval(input('param.goods_commonid'));

        $data = array();
        $data['result'] = true;
        
        //获取商品具体信息用于显示
        $goods_model = model('goods');
        $condition = array();
        $condition['goods_commonid'] = $goods_commonid;
        $goods_list = $goods_model->getGoodsOnlineList($condition);

        if (empty($goods_list)) {
            $data['result'] = false;
            $data['message'] = lang('param_error');
            echo json_encode($data);
            die;
        }


        $goods_info = $goods_list[0];
        $data['goods_id'] = $goods_info['goods_id'];
        $data['goods_commonid'] = $goods_info['goods_commonid'];
        $data['goods_name'] = $goods_info['goods_name'];
        $data['goods_price'] = $goods_info['goods_price'];
        $data['goods_image'] = goods_thumb($goods_info, 240);
        $data['goods_href'] = url('Goods/index', array('goods_id' => $goods_info['goods_id']));

        echo json_encode($data);
        die;
    }
    
    /**
     * 获取卖家栏目列表,针对控制器下的栏目
     */
    protected function getAdminItemList() {
        $menu_array = array(
            array(
                'name' => 'index',
                'text' => '分销设置',
                'url' => url('Inviter/setting')
            ),
            array(
                'name' => 'goods',
                'text' => '分销商品',
                'url' => url('Inviter/goods')
            ),
            array(
                'name' => 'goods_add',
                'text' => '添加分销商品',
                'url' => url('Inviter/goods_add')
            ),
            array(
                'name' => 'member',
                'text' => '分销员管理',
                'url' => url('Inviter/member')
            ),
            array(
                'name' => 'memberclass',
                'text' => '分销员等级',
                'url' => url('Inviter/memberclass')
            ),
            array(
                'name' => 'order',
                'text' => '分销订单',
                'url' => url('Inviter/order')
            ),
        );
        return $menu_array;
    }
}