<?php

// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 王泽彬 <wangzebin2@126.com>
// +----------------------------------------------------------------------

namespace think\view\driver;

use Smarty as BasicSmarty;
use think\App;
use think\facade\Log;
use think\helper\Str;
use think\Request;
use think\template\exception\TemplateNotFoundException;

class Smarty
{
    private $template = null;
    private $app;
    // 模板引擎参数
    protected $config = [
        // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写 3 保持操作方法
        'auto_rule'     => 1,
        // 视图目录名
        'view_dir_name' => 'view',
        // 模板起始路径
        'view_path'     => '',
        // 模板文件后缀
        'view_suffix'   => 'html',
        // 模板文件名分隔符
        'view_depr'     => DIRECTORY_SEPARATOR,
        // 是否开启模板编译缓存,设为false则每次都会重新编译
        'tpl_cache'     => true,
    ];

    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;
        $this->config = array_merge($this->config, (array) $config);
        $this->template = new BasicSmarty();
        $this->template->setLeftDelimiter($this->config['tpl_begin']);
        $this->template->setRightDelimiter($this->config['tpl_end']);
        $this->template->setCaching($this->config['tpl_cache']);
        $this->template->setForceCompile(!$this->config['tpl_cache']); #是否强制编译
        //$this->template->setTemplateDir($this->config['view_dir_name']); #设置模板目录
        $this->template->merge_compiled_includes = true; #合并编译导入

        $cacheDir = $this->app->getRuntimePath() . 'tplcache' . $this->config['view_depr'];
        $compileDir = $this->app->getRuntimePath() . 'compilecache' . $this->config['view_depr'];
        $this->template->setCacheDir($cacheDir); #设置缓存目录
        $this->template->setCompileDir($compileDir); #设置编译目录
    }

    /**
     * 检测是否存在模板文件
     * @access public
     * @param  string $template 模板文件或者模板规则
     * @return bool
     */
    public function exists($template)
    {
        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }

        return is_file($template);
    }

    /**
     * 渲染模板文件
     * @access public
     * @param string    $template 模板文件
     * @param array     $data 模板变量
     * @return void
     */
    public function fetch($template, $data = []) {

        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }
        // 模板不存在 抛出异常
        if (!is_file($template)) {
            throw new TemplateNotFoundException('template not exists:' . $template, $template);
        }

        // 记录视图信息
        $this->app->isDebug() && Log::record('[ VIEW ] ' . $template . ' [ ' . var_export(array_keys($data), true) . ' ]', 'info');

        !empty($template) && $this->template->assign($data);
        $output=null;
        if(array_key_exists('tpl_replace_string',$this->config)){
            // 定义模板常量
            $default=$this->config['tpl_replace_string'];
            //解析
            $output= str_replace(array_keys($default), array_values($default), $this->template->fetch($template));
        }else{
            //解析
            $output= $this->template->fetch($template);
        }
        echo $output;
    }

    /**
     * 渲染模板内容
     * @access public
     * @param string    $template 模板内容
     * @param array     $data 模板变量
     * @return void
     */
    public function display($template, $data = []) {
        return $this->fetch($template, $data);
    }

    /**
     * 配置模板引擎
     * @access private
     * @param  array  $config 参数
     * @return void
     */
    public function config($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取模板引擎配置
     * @access public
     * @param  string  $name 参数名
     * @return void
     */
    public function getConfig($name)
    {
        return $this->config[$name];
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param  string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template)
    {
        //设置模板路径
        $this->setViewPath($template);

        // 分析模板文件规则
        $request = $this->app['request'];
        $depr = $this->config['view_depr'];

        if (strpos($template, '@')) {
            // 跨模块调用
            list($app, $template) = explode('@', $template);
        }

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = $request->controller();

            if (strpos($controller, '.')) {
                $pos        = strrpos($controller, '.');
                $controller = substr($controller, 0, $pos) . '.' . Str::snake(substr($controller, $pos + 1));
            } else {
                $controller = Str::snake($controller);
            }

            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认模板渲染规则定位
                    if (2 == $this->config['auto_rule']) {
                        $template = $request->action(true);
                    } elseif (3 == $this->config['auto_rule']) {
                        $template = $request->action();
                    } else {
                        $template = Str::snake($request->action());
                    }

                    $template = str_replace('.', $this->config['view_depr'], $controller) . $depr . $template;
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', $this->config['view_depr'], $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        $path=$this->template->template_dir[0];

        return $path . ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');
    }

    /**
     * 设置模板路径
     */
    private function setViewPath($template){
        // 获取视图根目录
        $viewPath='';
        $view=$this->config['view_dir_name'];
        if (strpos($template, '@')) {
            // 跨模块调用
            list($app, $template) = explode('@', $template);
            $viewPath = $this->app->getAppPath().$app.$this->config['view_depr'].$view.$this->config['view_depr'];
        }else{
            //单应用
            $path1 = $this->app->getRootPath().$view.$this->config['view_depr'];
            //多应用
            $path2 = $this->app->getBasePath().$view.$this->config['view_depr'];
            if(is_dir($path1)){
                $viewPath=$path1;
            }else{
                $viewPath=$path2;
            }
        }

        if(empty($viewPath) || !is_dir($viewPath)){
            throw new TemplateNotFoundException('template not exists:' . $viewPath, $template);
        }

        //设置模板路径
        $this->template->setTemplateDir($viewPath);
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->template, $method], $params);
    }

}