<?php

namespace OagBundle\Tests\Controller;

use Doctrine\ORM\EntityManager;
use OagBundle\Entity\OagFile;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

class CoveControllerTest extends AbstractControllerTest {

  /**
   * @var Array
   */
  protected $filelist = ['basic_iati_unordered_valid.csv'];

  public function testIndex() {
    $oagfile = $this->em->getRepository(OagFile::class)
      ->findOneBy(array('documentName' => 'basic_iati_unordered_valid.csv'));

    $client = static::createClient();
    $crawler = $client->request('GET', '/cove/' . $oagfile->getId());
  }

}

