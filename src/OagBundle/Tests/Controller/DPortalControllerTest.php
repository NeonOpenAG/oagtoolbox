<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use OagBundle\Entity\OagFile;
use OagBundle\Service\DPortal;

class DPortalControllerTest extends AbstractControllerTest {

  /**
   * @var Array
   */
  protected $filelist = ['after_enrichment_activities.xml'];

  public function testIndex()
  {
    $client = static::createClient();
    $dportal = $this->getMockBuilder(DPortal::class)
      ->disableOriginalConstructor()
      ->getMock();
    $dportal->expects($this->any())
      ->method('visualise');
    $dportal->expects($this->any())
      ->method('isAvailable')
      ->willReturn(true);

    $client->getContainer()->set(DPortal::class, $dportal);

    $uploadDir = $this->container->getParameter('oagfiles_directory');
    $xmldir = $this->container->getParameter('oagxml_directory');

    copy($uploadDir . '/after_enrichment_activities.xml', $xmldir . '/after_enrichment_activities.xml');

    $oagfile = $this->em->getRepository(OagFile::class)
      ->findOneBy(array('documentName' => 'after_enrichment_activities.xml'));
    $crawler = $client->request('GET', '/dportal/' . $oagfile->getId());
  }

}
