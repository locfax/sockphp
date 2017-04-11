<?php

define('APPKEY', 'Sockphp');
define('PSROOT', __DIR__);

require PSROOT . '/vendor/autoload.php';

require PSROOT . '/config/base.inc.php';
require PSROOT . '/config/' . strtolower(APPKEY) . '.inc.php';

if (!extension_loaded('swoole')) {
    throw new \Exception('Require php extension "swoole"');
}

function app_start($config) {

    $app = new \Sock\Application();

    $app->steup(APP); //注册命名空间

    $server = new \swoole_websocket_server($config['server']['host'], $config['server']['port']);

    $server->set($config['setting']);

    $server->on('Request', function ($request, $response) {
        //请求过滤
        if ($request->server['request_uri'] == '/favicon.ico') {
            $response->end('');
            return;
        }
        $response->end("<h1>Hello Swoole. #" . rand(1000, 9999) . "</h1><br>");
    });

    $server->on('open', function ($server, $request) {
        echo "server: handshake success with fd{$request->fd}\n";
    });

    $server->on('message', function ($server, $frame) use ($app) {
        $data = $app->request($frame);
        if($data['fd']) {
            $server->push($data['fd'], output_json($data['ret']));
        }
    });

    $server->on('close', function ($server, $fd) {
        echo "client {$fd} closed\n";
    });

    $server->on('start', function () use ($config) {
        file_put_contents($config['server']['pid'], posix_getpid());
    });

    $server->on('shutdown', function () use ($config) {
        if (is_file($config['server']['pid'])) {
            unlink($config['server']['pid']);
        }
    });

    //开始服务
    $server->start();
}
