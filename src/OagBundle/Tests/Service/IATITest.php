<?php

use OagBundle\Entity\OagFile;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

class IATITest extends TestCase {

  protected $container;

  /**
   * @var \Doctrine\Common\Persistence\ObjectManager
   */
  protected $em;

  /**
   * @var OagFileService
   */
  protected $oagFileService;
  protected $testOagFile;

  public function setUp() {
    $kernel = new \AppKernel("test", true);
    $kernel->boot();
    $this->container = $kernel->getContainer();
    $this->em = $this->container
        ->get('doctrine')
        ->getManager();

    // Find one OagFile::IATI_DOCUMENT to test with.
    $this->testOagFile = $this->em
        ->getRepository(OagFile::class)
        ->findOneBy(array('fileType' => OagFile::OAGFILE_IATI_DOCUMENT));

    $this->oagFileService = $this->container->get(OagFileService::class);
    $this->oagFileService->setContainer($this->container);
  }

  public function testLoad() {
    $srvIATI = $this->container->get(IATI::class);
    $srvIATI->setContainer($this->container);

    $loadedFile = $srvIATI->load($this->testOagFile);
    $this->assertInstanceOf('SimpleXMLElement', $loadedFile);
  }

  public function testXpathNS() {
    $namespaceUri =  $this->container->getParameter('classifier')['namespace_uri'];

    $srvIATI = $this->container->get(IATI::class);
    $srvIATI->setContainer($this->container);

    $activity = $srvIATI->load($this->testOagFile)
        ->xpath('/iati-activities/iati-activity')[0];

    # Add a child tag for test purposes.
    $tag = $activity->addChild('openag:tag', '', $namespaceUri);
    $tags = $srvIATI->xpathNS($activity, './openag:tag');
    $this->assertEquals(
        [$tag],
        $tags,
        'Check that namespaced tag is found correctly.'
    );
  }

  public function testParseXML() {
    $srvIATI = $this->container->get(IATI::class);
    $srvIATI->setContainer($this->container);

    $iatiFileString = $this->oagFileService->getContents($this->testOagFile);

    $root = $srvIATI->parseXML($iatiFileString);
    $this->assertInstanceOf('SimpleXMLElement', $root);

    # Ensure that the namspace was added to each activity.
    $activities = $root->xpath('/iati-activities/iati-activity');
    foreach ($activities as $activity) {
      $activityDocAttributes = $activity->attributes();
      $this->assertObjectHasAttribute(
          'xmlns:openag',
          $activityDocAttributes,
          'Activities have openag namespace.'
      );
    }

    # Check that the method throws when expected.
    $this->expectExceptionMessage('String could not be parsed as XML');
    $srvIATI->parseXML('');
  }

  public function testToXML() {
      $srvIATI = $this->container->get(IATI::class);
      $srvIATI->setContainer($this->container);

      $iatiFileString = $this->oagFileService->getContents($this->testOagFile);

      $root = $srvIATI->parseXML($iatiFileString);
      $toXML = $srvIATI->toXML($root);
      $this->assertXmlStringEqualsXmlString($root->asXML(), $toXML);
  }

  public function testSummariseActivityToArray() {
      $srvIATI = $this->getMockBuilder(IATI::class)
          ->setMethods(array(
              'getActivityId',
              'getActivityTitle',
              'getActivityTags',
              'getActivityLocations'
          ))
          ->getMock();
      $srvIATI->setContainer($this->container);

      # Load a single activity for test purposes.
      $activity = $srvIATI->load($this->testOagFile)
          ->xpath('/iati-activities/iati-activity')[0];

      # Assert that the methods required to build the summary array are called.
      $srvIATI->expects($this->once())
          ->method('getActivityId');
      $srvIATI->expects($this->once())
          ->method('getActivityTitle');
      $srvIATI->expects($this->once())
          ->method('getActivityTags');
      $srvIATI->expects($this->once())
          ->method('getActivityLocations');

      # Assert that the correct keys are used in the summary array.
      $summarisedActivity = $srvIATI->summariseActivityToArray($activity);
      $this->assertArrayHasKey('id', $summarisedActivity);
      $this->assertArrayHasKey('name', $summarisedActivity);
      $this->assertArrayHasKey('tags', $summarisedActivity);
      $this->assertArrayHasKey('locations', $summarisedActivity);
  }


  public function testSummariseToArray() {}
  public function testGetFixtureData() {}
  public function testGetActivities() {}
  public function testGetActivityById() {}
  public function testGetActivityId() {}
  public function testGetActivityTitle() {}
  public function testGetActivityMapData() {}
  public function testGetActivityTags() {}
  public function testGetActivityLocations() {}
  public function testAddActivityTag() {}
  public function testAddActivityLocation() {}
  public function testRemoveActivityTag() {}
  public function testRemoveActivityLocation() {}

  /**
   * {@inheritDoc}
   */
  protected function tearDown() {
    parent::tearDown();

    $this->em->close();
    $this->em = null; // avoid memory leaks
  }
}
