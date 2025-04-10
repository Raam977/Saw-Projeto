<?php

namespace org\nameapi\ontology\input\context;

require_once(__DIR__.'/../../../../../../../src/org/nameapi/ontology/input/context/Context.php');

class ContextBuilderTest extends \PHPUnit_Framework_TestCase {

    public function testOne() {
        $context = Context::builder()->place('DE')->priority(Priority::REALTIME())->build();
        $this->assertEquals('DE', $context->getPlace());
        $this->assertEquals(Priority::REALTIME(), $context->getPriority());
        $this->assertEquals(array(), $context->getProperties());
    }

}
