<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ActivityControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/index');
    }

    public function testEnhance()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/enhance');
    }

    public function testMerge()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/merge');
    }

}
