<?php

namespace Sockphp;

class Controller {

    //用户信息
    protected $login_user = null;
    //当前控制器
    protected $server = null;
    //当前动作
    protected $frame = null;

    public function __construct($server, $frame) {
        $this->server = $server;
        $this->frame = $frame;
    }


    /**
     * @param $name
     * @param $arguments
     * @return string
     */
    public function __call($name, $arguments) {
        $res = array(
            'errcode' => 1,
            'errmsg' => 'Action ' . $name . '不存在!'
        );
        return json_encode($res);
    }

    /**
     * @param $controllerName
     * @param $actionName
     * @param bool $auth
     * @return bool
     */
    public function checkacl($controllerName, $actionName, $auth = AUTH) {
        return Rbac::check($controllerName, $actionName, $auth);
    }

}
