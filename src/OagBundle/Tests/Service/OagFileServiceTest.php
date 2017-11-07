<?php

use OagBundle\Entity\OagFile;
use OagBundle\Service\OagFileService;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

/**
 * Class OagFileServiceTest
 *
 * @group OagFileServiceTests
 */
class OagFileServiceTest extends TestCase
{

    protected $container;
    protected $kernel;

    public function setUp()
    {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->kernel = $kernel;
        $this->container = $kernel->getContainer();
    }

    public function testXmlFileName()
    {
        $fileService = $this->container->get(OagFileService::class);
        $fileService->setContainer($this->container);

        $oagFile = new OagFile();
        $oagFile->setDocumentName('test.txt');

        $data = $fileService->getXMLFileName($oagFile);
        $expectedData = 'test.' . date("Ymd_His") . '.xml';
        $this->assertEquals($expectedData, $data);
    }

    public function testGetPath()
    {
        $fileService = $this->container->get(OagFileService::class);

        $container = $this->getMockBuilder(\Symfony\Component\DependencyInjection\Container::class)
            ->setMethods(['getParameter'])
            ->getMock();

        // Make sure that both "oagxml_directory" and "oagfiles_directory" are
        // passed in as params.
        $container->expects($this->exactly(2))
            ->method('getParameter')
            ->withConsecutive(
                ['oagxml_directory'],
                ['oagfiles_directory']
            );

        $fileService->setContainer($container);

        $oagFile = new OagFile();
        $oagFile->setDocumentName('iatidoc.txt');

        $oagFile->setCoved(true);
        $path = $fileService->getPath($oagFile);
        $expectedPath = '/' . $oagFile->getDocumentName();
        $this->assertEquals($expectedPath, $path);

        $oagFile->setCoved(false);
        $path = $fileService->getPath($oagFile);
        $this->assertEquals($expectedPath, $path);
    }

    public function testGetContents()
    {
        $fileService = $this->getMockBuilder(OagFileService::class)
            ->setMethods(['getPath'])
            ->getMock();

        $fileService->setContainer($this->container);

        $testFilePath = $fileService->getContainer()
                ->getParameter('oagfiles_directory') . '/animalfarm.txt';

        // Return the file path on first call and an invalid path on the second.
        $fileService->expects($this->exactly(2))
            ->method('getPath')
            ->will(
                $this->onConsecutiveCalls($this->returnValue($testFilePath), '.')
            );

        $fileContents = $fileService->getContents(new OagFile());
        $this->assertStringEqualsFile($testFilePath, $fileContents);

        $this->expectExceptionMessage('OagFile contents not found at path');
        $fileService->getContents(new OagFile());
    }

}
