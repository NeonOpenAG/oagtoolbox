<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ErrorControllerControllerTest extends WebTestCase
{
    public function testDownload()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/download');
    }

}
