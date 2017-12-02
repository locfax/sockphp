<?php

namespace Sockphp;

class Controller {

    protected $server = null;
    protected $frame = null;

    public function init($frame) {
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

}
