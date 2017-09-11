<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\Common\Persistence\ObjectManager;
use OagBundle\Entity\OagFile;

/**
 * ./vendor/bin/simple-phpunit -c phpunit.xml --coverage-html web/test-coverage
 */
abstract class AbstractControllerTest extends WebTestCase {

  /**
   * @var Array
   */
  protected $filelist = [];

  /**
   * @var EntityManager
   */
  protected $em;

  /**
   * @var Container
   */
  protected $container;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    self::bootKernel();
    $this->container = static::$kernel->getContainer();
    $this->em = $this->container->get('doctrine')->getManager();

    // Clear old files
    $files = array();
    $uploadDir = $this->container->getParameter('oagfiles_directory');
    $xmldir = $this->container->getParameter('oagxml_directory');
    if (!is_dir($xmldir)) {
      mkdir($xmldir, 0755, true);
    }
    $files = glob($uploadDir . '/*');
    foreach ($files as $file) {
      unlink($file);
    }

    $repository = $this->em->getRepository(OagFile::class);
    $oagfiles = $repository->findAll();
    foreach ($oagfiles as $oagfile) {
      $this->em->remove($oagfile);
    }
    // Flush the entitiy manager to commit deletes.
    $this->em->flush();

    $assets = $this
      ->container
      ->getParameter('oag_test_assets_directory');
    foreach ($this->filelist as $file) {
      $file = $this
        ->container
        ->getParameter('oag_test_assets_directory') . '/' . basename($file);
      copy($file, $uploadDir . '/' . basename($file));

      $oagfile = new OagFile();
      $oagfile->setDocumentName(basename($file));
      $oagfile->setMimeType(mime_content_type($file));
      $oagfile->setUploadDate(new \DateTime('now'));

      $this->em->persist($oagfile);
    }
    $this->em->flush();
  }

}
