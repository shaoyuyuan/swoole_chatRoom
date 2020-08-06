<?php

    class WebSocketTest
    {
        public $server;

        public function __construct()
        {
            //设置Swoole/Table 用户进程间通信
            $table = new Swoole\Table(1024);
            $table->column('user_id', Swoole\Table::TYPE_STRING,30);
            $table->column('nickname', Swoole\Table::TYPE_STRING, 64);
            $table->create();

            $this->server = new Swoole\WebSocket\Server("0.0.0.0", 9501);

            $this->server->set([
                'worker_num' => 4,
                'heartbeat_idle_time' => 10,// 表示一个连接如果20秒内未向服务器发送任何数据，此连接将被强制关闭
                'heartbeat_check_interval' => 5 // 表示每2秒遍历一次
            ]);

            $this->server->table = $table;

            $this->server->on('open', function (Swoole\WebSocket\Server $server, $request) {

                $user = [
                    'user_id' => $request->get['user_id'],
                    'nickname' => $request->get['nickname'],
                ];
                $this->server->table->set($request->fd, $user);
//                $this->server->push($request->fd, json_encode(['userList'=>$this->pool,'message'=>'']));
                echo "server: handshake success with fd{$request->fd}\n";

            });

            $this->server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
//                echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
                //查询发送消息人的信息
                $user = $this->server->table->get($frame->fd);
                //对所有人发送消息
                foreach ($this->server->table as $fd => $v) {
                    // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                    if ($this->server->isEstablished($fd)) {
                        $this->server->push($fd, json_encode(['user'=>$user,'message'=>$frame->data]));
                    }
                }
            });

            $this->server->on('close', function ($ser, $fd) {
                //关闭连接，删除对应在线用户
                $this->server->table->del($fd);
                echo "client {$fd} closed\n";
            });

            $this->server->start();
        }

        /**
         * @return \Swoole\WebSocket\Server
         */
        public function getUserList()
        {
            $user = $this->server->table;
            die(json_encode($user));
        }

    }

new WebSocketTest();

