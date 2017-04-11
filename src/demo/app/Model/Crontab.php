<?php
namespace Model;

abstract class Crontab extends \Sock\Crontab {

    public function __construct($name = null) {
        if ($name) {
            $this->name = $name;
        }
        $this->setContextHandler(\Sock\Cacher::factory('redis'));
    }
}
