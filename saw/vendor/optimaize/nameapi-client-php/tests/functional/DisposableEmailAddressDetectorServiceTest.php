<?php

require '../../src/org/nameapi/client/services/ServiceFactory.php';
use org\nameapi\client\services\Host;
use org\nameapi\client\services\ServiceFactory;
use org\nameapi\ontology\input\context\Context;
use org\nameapi\ontology\input\context\Priority;


/**
 *
 * This is just one simple call. For all functional tests see the separate project at
 * https://github.com/optimaize/nameapi-client-php-functionaltests
 *
 *
 */
class DisposableEmailAddressDetectorServiceTest extends \PHPUnit_Framework_TestCase {

    public function testDea() {
        //setup code:
        $context = Context::builder()
            ->priority(Priority::REALTIME())
            ->build();
        $myApiKey = 'test'; //grab one from nameapi.org
        $serviceFactory = new ServiceFactory($myApiKey, $context, Host::http('rc50-api.nameapi.org'), '5.0');

        //the call:
        $deaDetector = $serviceFactory->emailServices()->disposableEmailAddressDetector();
        $result = $deaDetector->isDisposable("abcdefgh@10minutemail.com");

        //the assertions:
        $this->assertEquals('YES', (string)$result->getDisposable());
    }

}
