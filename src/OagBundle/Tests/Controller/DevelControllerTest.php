<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DevelControllerTest extends WebTestCase
{
    public function testOagfile()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/oagFile');
    }

    public function testEnhancementfile()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/enhancementFile');
    }

    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
    }

}
