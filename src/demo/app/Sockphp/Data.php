<?php

namespace Sockphp;

class Data extends \Sock\Controller {

    function act_index() {
        return ['fd' => $this->frame->fd, 'ret' => $this->data];
    }

}