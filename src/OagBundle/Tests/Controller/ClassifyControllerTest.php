<?php

namespace OagBundle\Tests\Controller;

use Doctrine\ORM\EntityManager;
use OagBundle\Entity\OagFile;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use OagBundle\Service\Classifier;
use OagBundle\Controller\ClassifyController;

class ClassifyControllerTest extends AbstractControllerTest {

  /**
   * @var Array
   */
  protected $filelist = ['animalfarm.txt', 'animalfarm.pdf', 'animalfarm.rtf', 'animalfarm.docx', 'after_enrichment_activities.xml'];

  public function testIndex() {
    $client = static::createClient();
    foreach ($this->filelist as $file) {
      $oagfile = $this->em->getRepository(OagFile::class)
        ->findOneBy(array('documentName' => $file));
      $crawler = $client->request('GET', '/classify/' . $oagfile->getId());
      $code = $client->getResponse()->getStatusCode();
      $this->assertTrue($code >= 200 && $code <= 399);
    }
  }

  public function testIndex404() {
    $client = static::createClient();

    $crawler = $client->request('GET', '/classify/9999999');
    $this->assertTrue($client->getResponse()->isNotFound());
  }

}
