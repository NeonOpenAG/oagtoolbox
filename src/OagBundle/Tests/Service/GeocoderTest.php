<?php

use OagBundle\Service\CSV;
use OagBundle\Service\Geocoder;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

class GeocoderTest extends TestCase {

    protected $container;

    public function setUp() {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }

    public function testGetStringFixtureData() {
        $srvCsv = $this->container->get(CSV::class);
        $geocoder = $this->container->get(Geocoder::class);

        $geocoder->setContainer($this->container);
        $tsv = $geocoder->getStringFixtureData();
        $parsed = $srvCsv->toArray($tsv, "\t");

        $this->assertInternalType('array', $parsed);
        $this->assertGreaterThan(0, count($parsed));
    }

    public function testGetXMLFixtureData() {
        $classifier = $this->container->get(Geocoder::class);
        $classifier->setContainer($this->container);
        $data = $classifier->getXMLFixtureData();
        $json = json_decode($data, true);
        $this->assertNotNull($json, 'No JSON returned from the geocoder');
        $this->assertTrue(count($json) >= 1);
    }

}
