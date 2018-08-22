<?php

namespace Sockphp;

class Controller
{
    protected $frame = null;

    /**
     * @param $frame
     */
    public function init($frame)
    {
        $this->frame = $frame;
    }

    /**
     * @param $name
     * @param $arguments
     * @return string
     */
    public function __call($name, $arguments)
    {
        $res = [
            'errcode' => 1,
            'errmsg' => 'Action ' . $name . '不存在!'
        ];
        return json_encode($res);
    }

}
