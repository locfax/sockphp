<?php

namespace Model\Cache;

class Category {

    use \Sockphp\Traits\Singleton;

    public function getdata() {
        $data = \Sockphp\Db::dbm('general')->findAll('category', '*', "1 ORDER BY sortby,catid ASC");
        if (empty($data)) {
            return '';
        }
        $data = array_index($data, 'catid');
        $data['tree'] = \Sockphp\Helper\Arrmap::getInstance()->to_tree($data, 'catid', 'upid', 'catid');
        return $data;
    }
}
