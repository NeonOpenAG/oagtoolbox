<?php

use OagBundle\Entity\OagFile;
use OagBundle\Entity\Tag;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

class IATITest extends TestCase
{

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

    public function setUp()
    {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->container = $kernel->getContainer();
        $this->em = $this->container
            ->get('doctrine')
            ->getManager();

        // Find one OagFile::IATI_DOCUMENT to test with.
        $this->testOagFile = $this->em
            ->getRepository(OagFile::class)
            ->findOneBy(array());

        $this->oagFileService = $this->container->get(OagFileService::class);
        $this->oagFileService->setContainer($this->container);
    }

    public function testLoad()
    {
        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);

        $loadedFile = $srvIATI->load($this->testOagFile);
        $this->assertInstanceOf('SimpleXMLElement', $loadedFile);
    }

    public function testXpathNS()
    {
        $namespaceUri = $this->container->getParameter('classifier')['namespace_uri'];

        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);

        $activity = $srvIATI->load($this->testOagFile)
            ->xpath('/iati-activities/iati-activity')[0];

        # Add a child tag for test purposes.
        $tag = $activity->addChild('openag:tag', '', $namespaceUri);
        $tags = $srvIATI->xpathNS($activity, './openag:tag');
        $justAdded = end($tags);
        $this->assertEquals(
            $tag->asXML(),
            $justAdded->asXML(),
            'Check that namespaced tag is found correctly.'
        );
    }

    public function testParseXML()
    {
        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);

        $iatiFileString = $this->oagFileService->getContents($this->testOagFile);

        $root = $srvIATI->parseXML($iatiFileString);
        $this->assertInstanceOf('SimpleXMLElement', $root);

        # ensure that the namespace was added to the root <iati-activities> element
//        $this->assertArrayHasKey(
//            'openag',
//            $root->getDocNamespaces(FALSE, FALSE),
//            'Activities have openag namespace.'
//        );

        # Check that the method logs when expected.

        $srvIATI->parseXML('');
    }

    public function testToXML()
    {
        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);

        $iatiFileString = $this->oagFileService->getContents($this->testOagFile);

        $root = $srvIATI->parseXML($iatiFileString);
        $toXML = $srvIATI->toXML($root);
        $this->assertXmlStringEqualsXmlString($root->asXML(), $toXML);
    }

    public function testSummariseActivityToArray()
    {
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

        # Assert that the correct keys are used in the summary array.
        $summarisedActivity = $srvIATI->summariseActivityToArray($activity);
        $this->assertArrayHasKey('id', $summarisedActivity);
        $this->assertArrayHasKey('name', $summarisedActivity);
        $this->assertArrayHasKey('tags', $summarisedActivity);
    }

    public function testSummariseToArray()
    {
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
    public function testGetFixtureData()
    {
    }

    public function testGetActivities()
    {
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

    public function testGetActivityById()
    {
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
        $activityIdToCheck = (string)$activityToCheck->xpath('./iati-identifier')[0];

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

    public function testGetActivityId()
    {
        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);
        $mock = $this
            ->getMockBuilder('Traversable')
            ->setMethods(['xpath', 'key', 'next', 'valid', 'rewind', 'current'])
            ->getMock();

        # We expect the xpath method to be ran once with the expected param.
        # It will return the two choices.
        $expectedParam = './iati-identifier';
        $mock->expects($this->once())
            ->method('xpath')
            ->with($expectedParam)
            ->willReturn([0, 1]);

        $activities = $srvIATI->getActivityId($mock);

        # Assert that the first item found by the xpath is returned.
        $this->assertEquals(0, $activities);

    }

    public function testGetActivityTitle()
    {
        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);

        // test activity with narrative
        $loadedFile = $srvIATI->load($this->testOagFile);
        $activity = $srvIATI->getActivities($loadedFile)[0];
        $title = $srvIATI->getActivityTitle($activity);
        $this->assertNotEquals(null, $title);
        $this->assertInternalType('string', $title);
        $this->assertGreaterThan(0, strlen($title));

        // text activity without narrative
        $activity = new \SimpleXMLElement('<iati-activity></iati-activity>');
        $title = $srvIATI->getActivityTitle($activity);
        $this->assertNull($title, 'Title should be null when there is none in the activity');
    }

    public function testGetActivityDescription()
    {
        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);

        // test activity with narrative
        $loadedFile = $srvIATI->load($this->testOagFile);
        $activity = $srvIATI->getActivities($loadedFile)[0];
        $title = $srvIATI->getActivityDescription($activity);
        $this->assertNotEquals(null, $title);
        $this->assertInternalType('string', $title);
        $this->assertGreaterThan(0, strlen($title));

        // text activity without narrative
        $activity = new \SimpleXMLElement('<iati-activity></iati-activity>');
        $title = $srvIATI->getActivityDescription($activity);
        $this->assertNull($title, 'Description should be null when there is none in the activity');
    }

    /**
     * Open to cool ideas to test these functions more individually without
     * needless code repetition. The fact that the state is always stored as XML
     * should keep this test worthy for now.
     *
     * @dataProvider tagManipulationProvider
     */
    public function testTagManipulation($exampleTag)
    {
        $srvIATI = $this->container->get(IATI::class);
        $srvIATI->setContainer($this->container);

        $mockActivity = new \SimpleXMLElement('<iati-activity></iati-activity>');

        // add our tag
        $srvIATI->addActivityTag(
            $mockActivity,
            $exampleTag
        );

        // check it's there
        $tags = $srvIATI->getActivityTags($mockActivity);
        $this->assertEquals(1, count($tags));

        // check its attributes survived the conversion to and from XML
        $addedTag = $tags[0];
        $this->assertEquals($exampleTag, $addedTag);

        // remove it but specify the wrong one
        $srvIATI->removeActivityTag($mockActivity, new Tag());
        $tags = $srvIATI->getActivityTags($mockActivity);
        $this->assertEquals(1, count($tags)); // it should still be there

        // remove it but get it right this time
        $srvIATI->removeActivityTag($mockActivity, $exampleTag);
        $tags = $srvIATI->getActivityTags($mockActivity);
        $this->assertEquals(0, count($tags)); // it should have gone
    }

    public function tagManipulationProvider()
    {
        // it would nice to be able to use $this->container - any ideas?
        // https://stackoverflow.com/a/42161440
        $kernel = new \AppKernel("test", true);
        $kernel->boot();

        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $tagRepo = $kernel->getContainer()->get('doctrine')->getRepository(Tag::class);

        $vocab = $kernel->getContainer()->getParameter('classifier')['vocabulary'];
        $vocabUri = $kernel->getContainer()->getParameter('classifier')['vocabulary_uri'];

        $descriptions = array(
            array(
                'description' => 'test description 1',
                'code' => 'abc123',
                'vocabulary' => $vocab,
                'vocabulary_uri' => $vocabUri
            ),
            array(
                'description' => 'test description 2',
                'code' => 'abc123',
                'vocabulary' => 'test',
                'vocabulary_uri' => null
            )
        );

        $cases = array();

        foreach ($descriptions as $description) {
            $arguments = array();

            $tag = $tagRepo->findOneBy($description);
            if (!$tag) {
                $tag = new Tag();
                $tag->setDescription($description['description']);
                $tag->setCode($description['code']);
                $tag->setVocabulary($description['vocabulary'], $description['vocabulary_uri']);
                $em->persist($tag);
            }

            $arguments[] = $tag;
            $cases[] = $arguments;
        }
        $em->flush();

        return $cases;
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown()
    {
        parent::tearDown();

        $this->em->close();
        $this->em = null; // avoid memory leaks
    }

}
