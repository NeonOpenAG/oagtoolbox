<?php

use OagBundle\Service\Cove;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

class CoveTest extends TestCase {

  protected $container;

  public function setUp() {
    $kernel = new \AppKernel("test", true);
    $kernel->boot();
    $this->container = $kernel->getContainer();
  }

  public function testGetFixtureData() {
    $classifier = $this->container->get(Cove::class);
    $classifier->setContainer($this->container);
    $data = $classifier->getFixtureData();

    $json = json_decode($data, true);

    $this->assertNotNull($json, 'No JSON returned from classifier');
    $this->assertTrue(count($json) >= 1);
  }

}
