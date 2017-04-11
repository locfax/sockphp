<?php

namespace Sock;

use Sock\Exception;

class Application {

    const _dCTL = 'ctl';
    const _dACT = 'act';
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
     * @param $path
     * @param $framedata
     * @param $frame
     * @return string
     */
    public function request($path, $framedata, $frame) {
        $data = $this->dispatching($path, $framedata, $frame);
        $this->finish();
        return $data;
    }

    /**
     * @param $request
     * @param $response
     */
    public function dispatching($path, $framedata, $frame) {
        $router = Route::parse_routes($path);

        $_controllerName = array_shift($router);
        $_actionName = array_shift($router);

        $controllerName = preg_replace('/[^a-z0-9_]+/i', '', $_controllerName);
        $actionName = preg_replace('/[^a-z0-9_]+/i', '', $_actionName);
        if (defined('AUTH') && AUTH) {
            $allow = Rbac::check($controllerName, $actionName, AUTH);
            if (!$allow) {
                return '["msg":"你没有权限访问 "]';
            }
        }
        return $this->execute($controllerName, $actionName, $framedata, $frame);
    }

    /**
     * @param $controllerName
     * @param $actionName
     * @param $framedata
     * @param $frame
     */
    private function execute($controllerName, $actionName, $framedata, $frame) {
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::_actionPrefix . $actionName;

        $controllerClass = self::_controllerPrefix . APPKEY . '\\' . $controllerName;
        try {
            $controller = new $controllerClass($framedata, $frame);
            return call_user_func([$controller, $actionMethod]);
        } catch (Exception\Exception $exception) { //普通异常
            return $this->exception($exception);
        } catch (Exception\DbException $exception) { //db异常
            return $this->exception($exception);
        } catch (Exception\CacheException $exception) { //cache异常
            return $this->exception($exception);
        } catch (\ErrorException $exception) {
            return $this->exception($exception);
        } catch (\Throwable $exception) { //PHP7
            return $this->exception($exception);
        }
    }

    /**
     * @param $exception
     * @param $response
     */
    private function exception($exception) {
        $data = $this->exception2str($exception);
        $res = ['errcode' => 1 , 'errmsg' => $data];
        return json_encode($res);
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
     * @param $namespace
     * @param $path
     */
    public function rootnamespace($namespace, $path) {
        $namespace = trim($namespace, '\\');
        $path = rtrim($path, '/');
        $loader = function ($classname) use ($namespace, $path) {
            if ($namespace && stripos($classname, $namespace) !== 0) {
                return false;
            }
            $file = trim(substr($classname, strlen($namespace)), '\\');
            $file = $path . '/' . str_replace('\\', '/', $file) . '.php';
            if (!is_file($file)) {
                throw new Exception\Exception($file . '不存在');
            }
            require $file;
            return true;
        };
        spl_autoload_register($loader);
    }

}