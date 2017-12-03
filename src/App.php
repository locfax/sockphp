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
     * @param $frame
     * @return mixed
     */
    public function request($frame) {
        $res = $this->dispatching($frame);
        $this->finish();
        return $res;
    }

    /**
     * @param $frame
     * @return mixed
     */
    public function dispatching($frame) {
        $data = json_decode($frame->data, true);
        if (!is_array($data) || !isset($data['type'])) {
            return array('fd' => $frame->fd, 'data' => 'Err: ' . $frame->data);
        }
        $router = $this->parse_routes($data['type']);

        if (is_array($router)) {
            $controllerName = array_shift($router);
            $actionName = array_shift($router);
        } else {
            $controllerName = getini('site/defaultController');
            $actionName = getini('site/defaultAction');
        }
        return $this->execute($controllerName, $actionName, $frame);
    }

    /**
     * @param $controllerName
     * @param $actionName
     * @param $frame
     * @return mixed
     */
    private function execute($controllerName, $actionName, $frame) {
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
            $controller->init($frame);
            return call_user_func([$controller, $actionMethod]);
        } catch (Exception\DbException $exception) {
            return array('fd' => $frame->fd, 'data' => $this->Exception2str($exception));
        } catch (\Exception $exception) { //db异常
            return array('fd' => $frame->fd, 'data' => $this->Exception2str($exception));
        } catch (\Throwable $exception) { //PHP7
            return array('fd' => $frame->fd, 'data' => $this->Exception2str($exception));
        }
    }

    private function parse_routes($uri) {
        static $routes = null;
        if (strpos($uri, 'index.php') !== false) {
            $uri = substr($uri, strpos($uri, 'index.php') + 10);
        }
        if (!$uri) {
            return false;
        }
        if (!$routes) {
            $routes = Context::config(APPKEY, 'route');
            $_routes = [];
            foreach ($routes as $key => $val) {
                $key = str_replace([':any', ':num'], ['[^/]+', '[0-9]+'], $key);
                $_routes[$key] = $val;
            }
            $routes = $_routes;
            $_routes = null;
        }
        foreach ($routes as $key => $val) {
            if (preg_match('#' . $key . '#', $uri, $matches)) {
                if (strpos($val, '$') !== FALSE && strpos($key, '(') !== FALSE) {
                    $val = preg_replace('#' . $key . '#', $val, $uri);
                }
                $req = explode('/', $val);
                return $req;
            }
        }
        return false;
    }

    /**
     * @param mixed $exception
     * @return string
     */
    private function Exception2str($exception) {
        $output = '<h3>' . $exception->getMessage() . '</h3>';
        $output .= '<p>' . nl2br($exception->getTraceAsString()) . '</p>';
        if ($previous = $exception->getPrevious()) {
            $output = $this->Exception2str($previous) . $output;
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