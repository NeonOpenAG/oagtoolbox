<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OagFileControllerTest extends WebTestCase
{

    public function testIati()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/oagFile/iati/5');
    }

    public function testSource()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/oagFile/source/4');
    }

    public function testEnhancement()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/oagFile/enhancement/3');
    }

}
