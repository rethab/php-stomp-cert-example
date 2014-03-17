<?php

require_once('FuseSource/Stomp/Stomp.php');
require_once('FuseSource/Stomp/Frame.php');
require_once('FuseSource/Stomp/ExceptionInterface.php');
require_once('FuseSource/Stomp/Exception/StompException.php');

error_reporting(E_ALL);

$cafile = './oldca.crt';
$local_cert = './php-client-chain.pem';

var_dump(openssl_x509_parse(file_get_contents($local_cert)));

$opts = array(
    'ssl' => array(
        'local_cert' => $local_cert 
    )
);

$con = new FuseSource\Stomp\Stomp('ssl://localhost:61613', $opts);
$con->connect();

$body = json_encode(array('message' => array('content' => 'hello, world')));
$headers = array('persistent' => 'true');
var_dump($con->send('/topic/TestQueue', $body, $headers));
