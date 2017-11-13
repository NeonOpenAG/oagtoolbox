<?php

use OagBundle\Service\Classifier;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

/**
 * @group ClassifierTests
 */
class ClassifierTest extends TestCase
{

    /**
     * @var \Symfony\Component\DependencyInjection\Tests\ProjectServiceContainer
     */
    protected $container;

    public function setUp()
    {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }


    public function testProcessUri()
    {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);

        $processed = $classifier->processUri();
        $this->assertJson(json_encode($processed));
    }

    /**
     * @dataProvider parseUriDataProvider
     */
    public function testParseUri($uri, $assertParsed)
    {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);

        $parsedUri = $classifier->parseUri($uri);
        $this->assertEquals($assertParsed, $parsedUri);
    }

    public function parseUriDataProvider()
    {
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

    public function testIsAvailable()
    {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);

        $uri = $classifier->getUri('xml');
        $parsedUri = $classifier->parseUri($uri);
        $host = $parsedUri['host'];
        $port = $parsedUri['port'];

        $sock = socket_create(AF_INET, SOCK_STREAM, 0);

        // Bind the socket to an address/port
        // TODO SUPER-unreliable, change soon
        @socket_bind($sock, $host, $port);
        socket_set_nonblock($sock);

        // Start listening for connections
        socket_listen($sock);

        // Accept incoming requests and handle them as child processes.
        socket_accept($sock);

        $result = $classifier->isAvailable();
        $this->assertTrue($result);

        socket_shutdown($sock);
    }

    public function testProcessXML()
    {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);

        $fixtureData = $classifier->getXMLFixtureData();
        $processed_xml = $classifier->processXML($fixtureData);

        $this->assertArrayHasKey('data', $processed_xml);
        $this->assertArrayHasKey('status', $processed_xml);
    }

    public function testProcessString()
    {
        $classifier = $this->getMockBuilder(Classifier::class)
            ->setMethods(['isAvailable'])
            ->getMock();
        $classifier->setContainer($this->container);

        // Assert that the correct fixture data is returned.
        $classifier->expects($this->at(0))
            ->method('isAvailable')
            ->willReturn(false);

        // Assert that the correct data is returned from Apiary.
        $classifier->expects($this->at(1))
            ->method('isAvailable')
            ->willReturn(true);

        $notAvailableResult = $classifier->processString('');
        $this->assertJson(json_encode($notAvailableResult));


        $availableResult = $classifier->processString('');
        $this->assertJson(json_encode($availableResult));
    }

    public function testGetStringFixtureData()
    {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);
        $data = $classifier->getStringFixtureData();

        $this->assertJson($data);
    }

    public function testGetXMLFixtureData()
    {
        $classifier = $this->container->get(Classifier::class);
        $classifier->setContainer($this->container);
        $data = $classifier->getXMLFixtureData();

        $this->assertJson($data);
    }
}
