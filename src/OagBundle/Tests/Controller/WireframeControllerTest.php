<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WireframeControllerTest extends WebTestCase
{
    public function testUpload()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/upload');
    }

    public function testClassifier()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/classifier');
    }

    public function testClassifiersuggestion()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/classifierSuggestion');
    }

    public function testGeocoder()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/geocoder');
    }

    public function testGeocodersuggestion()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/geocoderSuggestion');
    }

    public function testImproveryourdata()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/improverYourData');
    }

}
