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

  public function testSummariseToArray() {
      $srvIATI = $this->getMockBuilder(IATI::class)
          ->setMethods(array('summariseActivityToArray'))
          ->getMock();
      $srvIATI->setContainer($this->container);
      $root = $srvIATI->load($this->testOagFile);
      $activities = $root
          ->xpath('/iati-activities/iati-activity');

      # Assert that the methods required to build the summary array are called.
      $srvIATI->expects($this->exactly(count($activities)))
          ->method('summariseActivityToArray');

      $summary = $srvIATI->summariseToArray($root);
      $this->assertEquals(count($activities), count($summary));
  }

  // Not sure if we should unit test fixture data methods.
  public function testGetFixtureData() {}

  public function testGetActivities() {
      $srvIATI = $this->container->get(IATI::class);
      $srvIATI->setContainer($this->container);
      $mock = $this
          ->getMockBuilder('Traversable')
          ->setMethods(['xpath', 'key', 'next', 'valid', 'rewind', 'current'])
          ->getMock();

      # We expect the xpath method to be ran once with the expected param.
      $expectedParam = '/iati-activities/iati-activity';
      $mock->expects($this->once())
          ->method('xpath')
          ->with($expectedParam)
          ->willReturn([0]);

      $activities = $srvIATI->getActivities($mock);

      # Assert that the result of xpath is directly returned without modification.
      $this->assertNotEmpty($activities);
  }

  public function testGetActivityById() {
      $srvIATI = $this->getMockBuilder(IATI::class)
          ->setMethods(array('getActivities', 'getActivityId'))
          ->enableProxyingToOriginalMethods()
          ->getMock();
      $srvIATI->setContainer($this->container);


      $iatiFileString = $this->oagFileService->getContents($this->testOagFile);

      $root = $srvIATI->parseXML($iatiFileString);

      # Load a single activity for test purposes.
      $activities = $root->xpath('/iati-activities/iati-activity');

      $activityToCheck = $activities[0];
      $activityIdToCheck = (string) $activityToCheck->xpath('./iati-identifier')[0];

      # Assert that the methods required to find the activity ID are called.
      $srvIATI->expects($this->exactly(2))
          ->method('getActivities')
          ->willReturn($activities);

      # getActivityId will only be called once due to it being first in the list.
      $srvIATI->expects($this->exactly(count($activities) + 1))
          ->method('getActivityId')
          ->willReturn($activityIdToCheck);

      # Provide a valid ID and check to see if it's the activity we expect.
      $returnedActivity = $srvIATI->getActivityById($root, $activityIdToCheck);
      $this->assertEquals(
          $activityToCheck,
          $returnedActivity,
          'We receive the expected activity corresponding to the provided id.'
      );

      # Provide an invalid ID to check the return value.
      $returnedActivity = $srvIATI->getActivityById($root, '');
      $this->assertEmpty($returnedActivity);

  }


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
