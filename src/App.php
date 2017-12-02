<?php

namespace Sockphp;

class App {

    const _dCTL = 'c';
    const _dACT = 'a';
    const _controllerPrefix = '\\';
    const _actionPrefix = 'act_';

    private $handlers = [];

    /**
     * @param $root
     */
    public function steup($root) {
        $this->rootnamespace('\\', $root);
    }

    /**
     * @param $key
     * @param $handle
     */
    public function setHandle($key, $handle) {
        $this->handlers[$key] = $handle;
    }

    /**
     * @param $key
     * @param $param
     * @return bool|mixed
     */
    public function doHandle($key, $param) {
        if (!isset($this->handlers[$key])) {
            return true;
        }
        return call_user_func($this->handlers[$key], $param);
    }

    private function finish() {
        DB::close();
    }
    
    /**
     * @param $server
     * @param $frame
     */
    public function request($server, $frame) {
        $this->dispatching($server, $frame);
        $this->finish();
    }

    /**
     * @param $server
     * @param $frame
     * @return mixed
     */
    public function dispatching($server, $frame) {
        $framedata = json_decode($frame->data, true);
        if (!is_array($framedata) || !isset($framedata['type'])) {
            $server->push($frame->fd, 'err:' . $frame->data);
            return;
        }
        $router = Route::parse_routes($framedata['type']);

        if (is_array($router)) {
            $controllerName = array_shift($router);
            $actionName = array_shift($router);
        } else {
            $controllerName = getini('site/defaultController');
            $actionName = getini('site/defaultAction');
        }
        $this->execute($controllerName, $actionName, $server, $frame);
    }

    /**
     * @param $controllerName
     * @param $actionName
     * @param $server
     * @param $frame
     */
    private function execute($controllerName, $actionName, $server, $frame) {
        static $controller_pool = array();
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::_actionPrefix . $actionName;

        $controllerClass = self::_controllerPrefix . APPKEY . '\\' . $controllerName;
        try {
            if (isset($controller_pool[$controllerClass])) {
                $controller = $controller_pool[$controllerClass];
            } else {
                $controller = new $controllerClass();
                $controller_pool[$controllerClass] = $controller;
            }
            $controller->init($server, $frame);
            call_user_func([$controller, $actionMethod]);
        } catch (\Exception $exception) { //db异常
            $this->exception($exception, $server, $frame);
        } catch (\Throwable $exception) { //PHP7
            $this->exception($exception, $server, $frame);
        }
    }

    /**
     * @param $exception
     * @param $server
     * @param $response
     */
    private function exception($exception, $server, $frame) {
        $exp = $this->exception2str($exception);
        $res = ['errcode' => 1, 'errmsg' => $exp];
        $server->push($frame->fd, \Sockphp\Util::output_json($res));
        $this->finish();
    }

    /**
     * @param mixed $exception
     * @return string
     */
    private function exception2str($exception) {
        $output = '<h3>' . $exception->getMessage() . '</h3>';
        $output .= '<p>' . nl2br($exception->getTraceAsString()) . '</p>';
        if ($previous = $exception->getPrevious()) {
            $output = $this->exception2str($previous) . $output;
        }
        return $output;
    }

    /**
     * @param $group
     * @param null $vars
     * @return mixed
     */
    public static function mergeVars($group, $vars = null) {
        static $_CDATA = array(APPKEY => array('dsn' => null, 'cfg' => null, 'data' => null));
        $appkey = APPKEY;
        if (is_null($vars)) {
            return $_CDATA[$appkey][$group];
        }
        if (is_null($_CDATA[$appkey][$group])) {
            $_CDATA[$appkey][$group] = $vars;
        } else {
            $_CDATA[$appkey][$group] = array_merge($_CDATA[$appkey][$group], $vars);
        }
        return true;
    }

    /**
     * @param $namespace
     * @param $path
     */
    public function rootnamespace($namespace, $path) {
        $namespace = trim($namespace, '\\');
        $path = rtrim($path, '/');
        $loader = function ($classname) use ($namespace, $path) {
            if ($namespace && stripos($classname, $namespace) !== 0) {
                return;
            }
            $file = trim(substr($classname, strlen($namespace)), '\\');
            $file = $path . '/' . str_replace('\\', '/', $file) . '.php';
            if (!is_file($file)) {
                throw new \Exception($file . '不存在');
            }
            require $file;
        };
        spl_autoload_register($loader);
    }

}