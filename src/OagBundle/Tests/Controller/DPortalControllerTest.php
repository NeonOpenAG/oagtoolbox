<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use OagBundle\Entity\OagFile;

class DPortalControllerTest extends AbstractControllerTest {

  /**
   * @var Array
   */
  protected $filelist = ['after_enrichment_activities.xml'];

  public function testIndex()
  {
    $client = static::createClient();

    $uploadDir = $this->container->getParameter('oagfiles_directory');
    $xmldir = $this->container->getParameter('oagxml_directory');

    copy($uploadDir . '/after_enrichment_activities.xml', $xmldir . '/after_enrichment_activities.xml');

    $oagfile = $this->em->getRepository(OagFile::class)
      ->findOneBy(array('documentName' => 'after_enrichment_activities.xml'));
    $crawler = $client->request('GET', '/dportal/' . $oagfile->getId());
  }

}
