<?php

  /**
  * Created by PhpStorm.
  * User: yanghailong
  * Date: 2020/5/11
  * Time: 10:52 AM
  */
 class Server
 {
     public $server;
     public $host;
     public $port;

      public function __construct($host, $port)
     {
         $this->host = $host;
         $this->port = $port;
         $this->start();
     }

      public function start()
     {
         $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
         socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1); //复用还处于 TIME_WAIT
         socket_bind($this->server, $this->host, $this->port); //细节性的处理自行完成
         socket_listen($this->server); //开始监听
     }

      public function listen($callback)
     {
         if (!is_callable($callback)) {
             throw new Exception('不是闭包');
         }
         while (true) {
             $client = socket_accept($this->server);//接收socket连接
             $buf = socket_read($client, 1024);//获取请求数据
             $response = call_user_func($callback, $buf); //回调$callback函数
             $this->response($response, $client);
             var_dump($buf);
             usleep(1000); //微妙为单位，1000000 微妙等于1秒
             socket_close($client);//关闭客户端连接
         }

          socket_close($this->server);//关闭socket连接
     }

      protected function response($content, $client)
     {

          //返回数据给客户端,响应处理
         $string = "HTTP/1.1 200 OK\r\n";
         $string .= "Content-Type: text/html;charset=utf-8\r\n";
         $string .= "Content-Length: " . strlen($content) . "\r\n\r\n";
         socket_write($client, $string . $content);
     }
 } 

