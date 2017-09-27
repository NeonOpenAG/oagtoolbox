<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AsyncControllerTest extends WebTestCase
{
    public function testGeocode()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/geocode');
    }

    public function testClassify()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/classify');
    }

}
