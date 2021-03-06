<?php

use OagBundle\Service\CSV;
use OagBundle\Service\Geocoder;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

class GeocoderTest extends TestCase
{

    protected $container;

    public function setUp()
    {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }

    public function testGetStringFixtureData()
    {
        $classifier = $this->container->get(Geocoder::class);
        $classifier->setContainer($this->container);
        $data = $classifier->getStringFixtureData();
        $json = json_decode($data, true);
        $this->assertNotNull($json, 'No JSON returned from the geocoder');
        $this->assertTrue(count($json) >= 1);
    }

    public function testGetXMLFixtureData()
    {
        $classifier = $this->container->get(Geocoder::class);
        $classifier->setContainer($this->container);
        $data = $classifier->getXMLFixtureData();
        $json = json_decode($data, true);
        $this->assertNotNull($json, 'No JSON returned from the geocoder');
        $this->assertTrue(count($json) >= 1);
    }

}
