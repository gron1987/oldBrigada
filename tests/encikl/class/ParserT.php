<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gron
 * Date: 1/12/13
 * Time: 6:58 PM
 */

include_once __DIR__ . "/../../../encikl/class/Parser.php";
include_once __DIR__ . "/ParserDBT.php";

class ParserT extends Parser
{
    public function __construct(){
        parent::__construct();
        $this->_iniData['links']['links'] = array();
        $this->_iniData['links']['links'][] = __DIR__ . "/../html/elki.html";
    }

    protected function _setupDB(){
        $this->_db = new ParserDBT();
    }

    public function disableCURL(){
        parent::$curlEnabled = false;
    }

    protected function _log($msg){}
}