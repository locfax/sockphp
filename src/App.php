<?php

namespace Sockphp;

use Sockphp\Exception;

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
        set_error_handler(function ($errno, $error, $file = null, $line = null) {
            if (error_reporting() & $errno) {
                throw new \ErrorException($error, $errno, $errno, $file, $line);
            }
            return true;
        });
        $this->rootnamespace('\\', $root);
    }

    private function finish() {
        try {
            Db::close();
        } catch (\ErrorException $e) {

        }
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
        $router = Route::parse_routes($framedata['todo']);

        $_controllerName = array_shift($router);
        $_actionName = array_shift($router);

        $controllerName = preg_replace('/[^a-z0-9_]+/i', '', $_controllerName);
        $actionName = preg_replace('/[^a-z0-9_]+/i', '', $_actionName);
        if (defined('AUTH') && AUTH) {
            $allow = Rbac::check($controllerName, $actionName, AUTH);
            if (!$allow) {
                $res = ['errcode' => 1, 'errormsg' => '你没有权限访问'];
                $server->push($frame->fd, output_json($res));
            }
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
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::_actionPrefix . $actionName;

        $controllerClass = self::_controllerPrefix . APPKEY . '\\' . $controllerName;
        try {
            $controller = new $controllerClass($server, $frame);
            call_user_func([$controller, $actionMethod]);
        } catch (Exception\Exception $exception) { //普通异常
            $this->exception($exception, $server, $frame);
        } catch (Exception\DbException $exception) { //db异常
            $this->exception($exception, $server, $frame);
        } catch (Exception\CacheException $exception) { //cache异常
            $this->exception($exception, $server, $frame);
        } catch (\ErrorException $exception) {
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
        $server->push($frame->fd, output_json($res));
    }

    /**
     * @param $exception
     * @return string
     */
    private function exception2str($exception) {
        $output = '<h3>' . $exception->getMessage() . '</h3>';
        $output .= '<p>' . nl2br($exception->getTraceAsString()) . '</p>';
        if ($previous = $exception->getPrevious()) {
            $output = $this->strexception($previous) . $output;
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
        if (is_null($vars)) {
            return $_CDATA[APPKEY][$group];
        }
        if (is_null($_CDATA[APPKEY][$group])) {
            $_CDATA[APPKEY][$group] = $vars;
        } else {
            $_CDATA[APPKEY][$group] = array_merge($_CDATA[APPKEY][$group], $vars);
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
                throw new Exception\Exception($file . '不存在');
            }
            require $file;
        };
        spl_autoload_register($loader);
    }

}