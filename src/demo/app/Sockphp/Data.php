<?php

namespace Sockphp;

class Data extends \Sockphp\Controller {

    function act_index() {
        return ['fd' => $this->frame->fd, 'ret' => $this->data];
    }

}