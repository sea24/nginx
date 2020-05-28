<?php
/**
 * Created by PhpStorm.
 * User: Sixstar-Peter
 * Date: 2018/11/14
 * Time: 21:04
 */

class Server{
    private  $_mainSocket;
    private  $_newSocket;
    private  $_EventBase;
    public   $pids;
    public  function  __construct()
    {
        //从配置文件
        $this->forkServer(); //创建服务监听
        file_put_contents('/tmp/master.pid',getmypid());  //
    }

    public function signalHandler($signo){
        switch ($signo) {
            case SIGTERM:
                echo 'kill';
                exit;
                break;
            case SIGHUP:
                //处理SIGHUP信号
                break;
            case SIGINT:
                //处理ctrl+c
                echo 'ctrl+c';
                exit;
                break;
            default:
                // 处理所有其他信号
        }
    }
    protected function  monitor(){
        //SIGINT ctrl+c
        //注册信号处理器，信号信号触发时，执行的闭包（进程关闭之后，回收主进程的pid文件）
        pcntl_signal(SIGINT,[$this,'signalHandler']);
        pcntl_signal(SIGTERM,[$this,'signalHandler']);

        while (1){
            pcntl_signal_dispatch(); //调用等待信号的处理器，触发信号事件，挂起状态
            //$pid=pcntl_wait($status,WUNTRACED); //等待子进程中断，防止子进程成为僵尸进程。
            //$status=0;
        }

    }


    public  function  forkServer($count=2){
        for ($i = 0; $i < $count; ++$i)
        {
            $pid = pcntl_fork();
            if ($pid < 0) {
                exit('生成子进程失败\n');
            } else if ($pid > 0) {
                // 父进程
                $this->pids[] = $pid;
            } else {
                $this->listen();
                exit;
            }
        }
        $this->monitor(); //监视

    }

    public  function  listen (){
        $tcp    = "0.0.0.0:9005";

        // 创建资源流上下文
        $context = stream_context_create([
            'socket' => [
                'backlog' => 102400
            ]
        ]);
        //监听客户端链接 + 设置端口重用
        stream_context_set_option($context , 'socket' , 'so_reuseport' , 1);
        stream_context_set_option($context,'socket','so_reuseaddr',1); //设置连接重用

        $this->_mainSocket = stream_socket_server($tcp , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN , $context);
        stream_set_blocking($this->_mainSocket , false);

        $this->_EventBase = new \EventBase();
        $event=new \Event( $this->_EventBase,$this->_mainSocket,Event::READ | Event::PERSIST,function (){
            $this->_newSocket = stream_socket_accept($this->_mainSocket);
            stream_set_blocking($this->_newSocket , false);//非阻塞

            //触发客户端事件
            $event=new \Event( $this->_EventBase , $this->_newSocket , Event::READ | Event::PERSIST  ,function($socket)use(&$event){
                $msg = fread($socket , 65535);
                // Check connection closed.检查连接是否关闭
                if ($msg === '') {

                    if ((feof($socket) || !is_resource($socket))) {
                        $event->del(); //删除事件
                        return null;
                    }
                }else{
                    $content = '<p>我是需要访问的内容</p>';
                    $header = "HTTP/1.1 200 OK\r\n";
                    $header .= "Content-Type: text/html;charset=utf-8\r\n";
                    $header .= "Connection: keep-alive\r\n";
                    $header .= "Content-Length: " . strlen($content) . "\r\n\r\n";
                    fwrite($socket,$header . $content);
                }
            },$this->_newSocket);
            $event->add(); //挂起事件
        });
        $event->add();
        $this->_EventBase->loop();

    }

}

$server=new Server();

