<?php
namespace Model;

abstract class Crontab extends \Sockphp\Crontab {

    public function __construct($name = null) {
        if ($name) {
            $this->name = $name;
        }
        $this->setContextHandler(\Sockphp\Cacher::factory('redis'));
    }
}
