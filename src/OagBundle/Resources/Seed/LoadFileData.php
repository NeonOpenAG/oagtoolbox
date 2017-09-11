<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OagBundle\Resources\Seed;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use OagBundle\Entity\OagFile;

/**
 * Description of LoadFileData
 *
 * @author tobias
 */
class LoadFileData implements FixtureInterface {

    public function load(ObjectManager $em) {
        $file = new OagFile();
        $file->setDocumentName('animalfarm.txt');
        $file->setFileType(OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT);
        $file->setMimeType('text/plain');
        $file->setUploadDate(new \DateTime('now'));
        $em->persist($file);

        $file = new OagFile();
        $file->setDocumentName('poobear.txt');
        $file->setFileType(OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT);
        $file->setMimeType('text/plain');
        $file->setUploadDate(new \DateTime('now'));
        $em->persist($file);

        $file = new OagFile();
        $file->setDocumentName('threelittlepigs.txt');
        $file->setFileType(OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT);
        $file->setMimeType('text/plain');
        $file->setUploadDate(new \DateTime('now'));
        $em->persist($file);

        $em->flush();

        $repo = $em->getRepository('OagBundle:OagFile');
        $animalfarm = $repo->findOneByDocumentName('animalfarm.txt');
        $threelittlepigs = $repo->findOneByDocumentName('poobear.txt');

        $file = new OagFile();
        $file->setDocumentName('ifad-agrovoc-tag.xml');
        $file->setFileType(OagFile::OAGFILE_IATI_DOCUMENT);
        $file->setMimeType('application/xml');
        $file->addEnhancingDocument($animalfarm);
        $file->addEnhancingDocument($threelittlepigs);
        $file->setUploadDate(new \DateTime('now'));
        $em->persist($file);

        $em->flush();
    }

}
