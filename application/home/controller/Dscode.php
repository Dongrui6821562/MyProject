<?php

/**
 * 系统代码检测
 */
/**
还需要新增  语言包中 在lang 未定义的

以及 CSS 在语言包中未定义的

**/
namespace app\home\controller;

use think\Controller;
use think\Log;

class Dscode extends Controller {

    public function _initialize() {
        parent::_initialize();

        //遍历语言包的目录
        $this->lang_directory_list = array(
            'home',
            'admin',
            'mobile',
        );
    }

    function index() {
        echo "<br/><br/>检测语言包是否重复定义<br/><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_lang', array('type' => 'home')) . "'>检测Home模块语言包中重复定义的语言</a><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_lang', array('type' => 'admin')) . "'>检测Admin模块语言包中重复定义的语言</a><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_lang', array('type' => 'mobile')) . "'>检测Mobile模块语言包中重复定义的语言</a><br/>";

        echo "<br/><br/>检测语言包在控制器以及模板中是否调用<br/><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_dir', array('type' => 'home')) . "'>检测Home模块语言包中重复定义的语言</a><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_dir', array('type' => 'admin')) . "'>检测Admin模块语言包中重复定义的语言</a><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_dir', array('type' => 'mobile')) . "'>检测Mobile模块语言包中重复定义的语言</a><br/>";
        
        echo "<br/><br/>检测模板中是否有语言包<br/><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_code', array('type' => 'home')) . "'>检测Home模块语言包中重复定义的语言</a><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_code', array('type' => 'admin')) . "'>检测Admin模块语言包中重复定义的语言</a><br/>";
        echo "<a target='_blank' href='" . url('Dscode/check_code', array('type' => 'mobile')) . "'>检测Mobile模块语言包中重复定义的语言</a><br/>";
    }
    
    
    /**
     * 检测当前模块下使用的语言包是否在lang中定义
     */
    function check_code() {
        exit('待完善');
        $lang_type = input('type');
        if (!in_array($lang_type, array('home', 'admin', 'mobile'))) {
            exit('参数错误');
        }

        $dir = APP_PATH . DS . $lang_type;
        $lang_dir = $dir . DS . 'lang';

        //获取语言包下所有文件目录绝对地址
        $this->read_file_list($lang_dir, $lang_files);
        
        $chche_key = 'checked_dir_' . $lang_type;
        $checked_dir = unserialize(cache($chche_key));
//        cache($chche_key, NULL);
//        halt($checked_dir);
        //检测每次执行检测语言包的个数。
        $check_times = 0;
        //获取单个语言包的定义,在语言包目录下是否重复。
        foreach ($lang_files as $lang_file) {
            $lang_array = $this->get_lang_defind($lang_file);
            foreach ($lang_array as $key => $lang) {
                if (isset($checked_dir['check']) && in_array($lang, $checked_dir['check'])) {
                    continue;
                }

                //判断语言在语言包出现的次数
                $times = $this->check_times_dirlang($lang, $dir,'dir');
                //规则不一样，因为等于0
                if ($times == 0) {
                    $checked_dir['error'][] = array('times' => $times, 'lang' => $lang);
                }
                //记录缓存
                $checked_dir['check'][] = $lang;
                if ($check_times < 100) {
                    $check_times++;
                } else {
                    cache($chche_key, serialize($checked_dir));
                    exit($lang_type . '继续执行刷新,已经检测' . count($checked_dir['check']));
                }
            }
        }
        cache($chche_key, NULL);
        halt($checked_dir);
        //获取单个语言包的定义，在语言包目录下是否出现。
    }
    
    

    /**
     * 检测当前控制器下语言包所有代码
     */
    function check_dir() {

        $lang_type = input('type');
        if (!in_array($lang_type, array('home', 'admin', 'mobile'))) {
            exit('参数错误');
        }

        $dir = APP_PATH . DS . $lang_type;
        $lang_dir = $dir . DS . 'lang';

        //获取语言包下所有文件目录绝对地址
        $this->read_file_list($lang_dir, $lang_files);
        
        $chche_key = 'checked_dir_' . $lang_type;
        $checked_dir = unserialize(cache($chche_key));
//        cache($chche_key, NULL);
//        halt($checked_dir);
        //检测每次执行检测语言包的个数。
        $check_times = 0;
        //获取单个语言包的定义,在语言包目录下是否重复。
        foreach ($lang_files as $lang_file) {
            $lang_array = $this->get_lang_defind($lang_file);
            foreach ($lang_array as $key => $lang) {
                if (isset($checked_dir['check']) && in_array($lang, $checked_dir['check'])) {
                    continue;
                }

                //判断语言在语言包出现的次数
                $times = $this->check_times_dirlang($lang, $dir,'dir');
                //规则不一样，因为等于0
                if ($times == 0) {
                    $checked_dir['error'][] = array('times' => $times, 'lang' => $lang);
                }
                //记录缓存
                $checked_dir['check'][] = $lang;
                if ($check_times < 100) {
                    $check_times++;
                } else {
                    cache($chche_key, serialize($checked_dir));
                    exit($lang_type . '继续执行刷新,已经检测' . count($checked_dir['check']));
                }
            }
        }
        cache($chche_key, NULL);
        halt($checked_dir);
        //获取单个语言包的定义，在语言包目录下是否出现。
    }

    /**
     * 检测语言包代码
     */
    function check_lang() {

        $lang_type = input('type');
        if (!in_array($lang_type, array('home', 'admin', 'mobile'))) {
            exit('参数错误');
        }

        $dir = APP_PATH . DS . $lang_type;
        $lang_dir = $dir . DS . 'lang';

        //获取语言包下所有文件目录绝对地址
        $this->read_file_list($lang_dir, $lang_files);

        $chche_key = 'checked_lang_' . $lang_type;

        $checked_lang = unserialize(cache($chche_key));
//        halt($checked_lang);
        //检测每次执行检测语言包的个数。
        $check_times = 0;
        //获取单个语言包的定义,在语言包目录下是否重复。
        foreach ($lang_files as $lang_file) {
            $lang_array = $this->get_lang_defind($lang_file);
            foreach ($lang_array as $key => $lang) {
                if (isset($checked_lang['check']) && in_array($lang, $checked_lang['check'])) {
                    continue;
                }

                //判断语言在语言包出现的次数
                $times = $this->check_times_dirlang($lang, $lang_dir);
                if ($times > 1) {
                    $checked_lang['error'][] = array('times' => $times, 'lang' => $lang);
                }
                //记录缓存
                $checked_lang['check'][] = $lang;
                if ($check_times < 302) {
                    $check_times++;
                } else {
                    cache($chche_key, serialize($checked_lang));
                    exit($lang_type . '继续执行刷新,已经检测' . count($checked_lang['check']));
                }
            }
        }
        cache($chche_key, NULL);
        halt($checked_lang);
        //获取单个语言包的定义，在语言包目录下是否出现。
    }

    /**
     * 检测语言当前目录下出现的次数
     * @param type $lang
     */
    function check_times_dirlang($lang, $dir, $type = 'lang') {
        $times = 0;
        $this->read_file_list($dir, $files);
        foreach ($files as $key => $file) {
            //获取当前文件后缀
            $ext = substr($file, strrpos($file, '.')+1);
            $content = file_get_contents($file);
            if ($type == 'lang') {
                //检测语言包的规则
                if (strripos($content, "['" . $lang . "']") !== FALSE) {
                    $times++;
                }
            } elseif ($type == 'dir') {
                if ($ext == 'php') {
                    if (strripos($content, "lang('" . $lang . "')") !== FALSE) {
                        $times++;
                    }
                } elseif($ext=='html'){
                    if (strripos($content, "." . $lang) !== FALSE) {
                        $times++;
                    }
                }else{
                    if (strripos($content,$lang) !== FALSE) {
                        $times++;
                    }
                }
            }
        }
        return $times;
    }

    /**
     * 获取当前文件定义的语言数组
     * @param type $lang_file
     */
    function get_lang_defind($lang_file) {
        $content = file_get_contents($lang_file);
//        halt($content);exit;
        $preg = "/\$lang[\(.+?)\']/i";
        $preg = "/lang\[\'(.+?)\']/is";
        preg_match_all($preg, $content, $arr);
//        halt($arr[1]);
        return $arr[1];
    }

    /**
     * 获取文件列表(所有子目录文件)
     *
     * @param string $path 目录
     * @param array $file_list 存放所有子文件的数组
     * @param array $ignore_dir 需要忽略的目录或文件
     * @return array 数据格式的返回结果
     */
    function read_file_list($path, &$file_list, $ignore_dir = array()) {
        $path = rtrim($path, '/');
        if (is_dir($path)) {
            $handle = @opendir($path);
            if ($handle) {
                while (false !== ($dir = readdir($handle))) {
                    if ($dir != '.' && $dir != '..') {
                        if (!in_array($dir, $ignore_dir)) {
                            if (is_file($path . '/' . $dir)) {
                                $file_list[] = $path . '/' . $dir;
                            } elseif (is_dir($path . '/' . $dir)) {
                                $this->read_file_list($path . '/' . $dir, $file_list, $ignore_dir);
                            }
                        }
                    }
                }
                @closedir($handle);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

}
