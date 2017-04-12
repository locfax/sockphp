<?php

namespace Sockphp;

class Main extends \Sockphp\Controller {

    function act_index() {
        $row = ['fd' => $this->frame->fd, 'ret' => $this->data];
        return $row;
    }
}