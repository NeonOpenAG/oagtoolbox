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

    public function testGetFixtureData() {
        $srvCsv = $this->container->get(CSV::class);
        $geocoder = $this->container->get(Geocoder::class);

        $geocoder->setContainer($this->container);
        $tsv = $geocoder->getFixtureData();
        $parsed = $srvCsv->toArray($tsv, "\t");

        $this->assertInternalType('array', $parsed);
        $this->assertGreaterThan(0, count($parsed));
    }

}
