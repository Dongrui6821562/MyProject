<?php

namespace app\crontab\controller;
use think\Controller;
use think\Log;

class BaseCron extends Controller {

    public function shutdown(){
        exit("run ".request()->controller()." success at ".date('Y-m-d H:i:s',TIMESTAMP)."\n");
    }

    public function __construct(){
        $config_list = rkcache('config', true);
        config($config_list);
        set_time_limit(600);
        error_reporting(E_ALL & ~E_NOTICE);
        register_shutdown_function(array($this,"shutdown"));
    }

    /**
     * 记录日志
     * @param unknown $content 日志内容
     * @param boolean $if_sql 是否记录SQL
     */
    protected function log($content, $if_sql = true) {

        Log::record('queue\\'.$content);
    }

    
}
?>
