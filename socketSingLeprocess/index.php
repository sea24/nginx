<?php
/**
 * Created by PhpStorm.
 * User: yanghailong
 * Date: 2020/5/11
 * Time: 10:52 AM
 */
include "server.php";

$server = new Server('0.0.0.0','9788');

$server->listen(function ($buf){

    return 'hello sea';
});
