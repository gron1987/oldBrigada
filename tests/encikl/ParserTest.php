<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gron
 * Date: 1/12/13
 * Time: 6:47 PM
 */

include_once __DIR__ . "/class/ParserT.php";

class ParserTest extends PHPUnit_Framework_TestCase
{
    public function testRunWithStandart(){
        $parser = new ParserT();
        $parser->disableCURL();
        $resultWithCurl = $parser->run();

        file_put_contents("elki.ser",serialize($resultWithCurl));

        $standard = file_get_contents( __DIR__ . "/serializations/elki.ser");
        $standard = unserialize($standard);
        $this->assertSame($standard, $resultWithCurl);
    }
}
