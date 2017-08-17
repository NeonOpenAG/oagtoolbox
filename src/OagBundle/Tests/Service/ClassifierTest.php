<?php

use OagBundle\Service\Classifier;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

/**
 * @group ClassifierTests
 */
class ClassifierTest extends TestCase {

    /**
    * @var \Symfony\Component\DependencyInjection\Tests\ProjectServiceContainer
    */
    protected $container;

    public function setUp() {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }


    public function testProcessUri() {}

    /**
     * @dataProvider parseUriDataProvider
     * @group failing
     */
    public function testParseUri($uri, $assertParsed) {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);

        $parsedUri = $classifier->parseUri($uri);
        $this->assertEquals($assertParsed, $parsedUri);
    }

    public function parseUriDataProvider() {
        return array(
            array('http://localhost:8011', array(
                'host' => 'localhost',
                'port' => '8011',
            )),
            array('https://localhost:443', array(
                'host' => 'localhost',
                'port' => '443',
            )),
            array('https://localhost', array(
                'host' => 'localhost',
                'port' => '443',
            )),
            array('http://localhost', array(
                'host' => 'localhost',
                'port' => '80',
            )),
        );
    }

    public function testIsAvailable() {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);

        $uri = $classifier->getUri('xml');
        $parsedUri = $classifier->parseUri($uri);
        $host = $parsedUri['host'];
        $port = $parsedUri['port'];

        $sock = socket_create(AF_INET, SOCK_STREAM, 0);

        // Bind the socket to an address/port
        socket_bind($sock, $host, $port);
        socket_set_nonblock($sock);

        // Start listening for connections
        socket_listen($sock);

        // Accept incoming requests and handle them as child processes.
        socket_accept($sock);

        $result = $classifier->isAvailable();
        $this->assertTrue($result);

        socket_shutdown($sock);
    }

    public function testProcessXML() {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);

        $processed_xml = $classifier->processXML();
        $this->assertJson($processed_xml);
    }

    /**
     * @group failing
     */
    public function testProcessString() {
        $classifier = $this->getMockBuilder(Classifier::class)
            ->setMethods(['isAvailable'])
            ->getMock();
        $classifier->setContainer($this->container);

        $classifier->method('isAvailable')
            ->willReturn(true);


        $result = $classifier->processString('swine');
        dump($result);

    }
    public function testExtractSectors() {}

    public function testGetStringFixtureData() {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);
        $data = $classifier->getStringFixtureData();

        $json = json_decode($data, true);

        $this->assertNotNull($json, 'No JSON returned from classifier');
        $this->assertTrue(count($json) >= 1);
    }

    public function testGetXMLFixtureData() {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);
        $data = $classifier->getXMLFixtureData();

        $json = json_decode($data, true);

        $this->assertNotNull($json, 'No JSON returned from classifier');
        $this->assertTrue(count($json) >= 1);
    }
}
