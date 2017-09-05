<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ActivityControllerTest extends WebTestCase
 {

    public function testEnhance()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/activity/4/XM-DAC-41108-1100001602');
        $code = $client->getResponse()->getStatusCode();

        $this->assertTrue($code >= 200 && $code <= 399);
    }

//    public function testMerge()
//    {
//        $client = static::createClient();
//
//        $crawler = $client->request('GET', '/activity/merge');
//        $code = $client->getResponse()->getStatusCode();
//        $this->assertTrue($code >= 200 && $code <= 399);
//    }
}
