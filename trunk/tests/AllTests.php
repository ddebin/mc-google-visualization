<?php
require_once 'init.php';

require_once 'ParserTest.php';
require_once 'VisualizationTest.php';

class AllTests {

    public static function suite() {
        $suite = new PHPUnit_Framework_TestSuite();
        $suite->addTestSuite('ParserTest');
        $suite->addTestSuite('VisualizationTest');
        return $suite;
    }
}

?>
