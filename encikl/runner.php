<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gron
 * Date: 1/5/13
 * Time: 8:36 PM
 */

if( !defined("__DIR__") ) define( "__DIR__", dirname(__FILE__) );

function __autoload($className){
    include __DIR__ . '/class/' . $className . '.php';
}

$parser = new Parser();
$parser->run();