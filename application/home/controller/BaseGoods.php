<?php

/*
 * 商品的类
 */

namespace app\home\controller;

use think\Controller;

class BaseGoods extends BaseMall {

    public function _initialize() {
        parent::_initialize();
        //输出会员信息
        $this->getMemberAndGradeInfo(false);
    }
}

?>
