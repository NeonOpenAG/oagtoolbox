<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OagFileControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/index');
    }

    public function testIati()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/iati');
    }

    public function testSource()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/source');
    }

    public function testEnhancement()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/enhancement');
    }

}
