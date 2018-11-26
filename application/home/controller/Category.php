<?php

namespace app\home\controller;

use think\Lang;
use think\Validate;

class Category extends BaseMall {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/'.config('default_lang').'/category.lang.php');
    }

    /*
     * 显示商品分类列表
     */

    public function goods() {
        //获取全部的商品分类
        //导航
        $nav_link = array(
            '0' => array('title' => lang('ds_index'), 'link' => HOME_SITE_URL),
            '1' => array('title' => lang('category_all_categories'))
        );
        $this->assign('nav_link_list', $nav_link);

        $this->assign('html_title', config('site_name') . ' - ' . lang('category_all_categories'));
        return $this->fetch($this->template_dir . 'goods_category');
    }


}
