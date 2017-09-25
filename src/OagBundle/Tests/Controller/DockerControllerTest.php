<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DockerControllerTest extends WebTestCase
{
    public function testList()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
    }

    public function testStart()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/start');
    }

    public function testStop()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/stop');
    }

}
