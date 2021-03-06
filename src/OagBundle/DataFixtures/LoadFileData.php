<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OagBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use OagBundle\Entity\EnhancementFile;
use OagBundle\Entity\OagFile;

/**
 * Description of LoadFileData
 *
 * @author tobias
 */
class LoadFileData extends Fixture
{

    public function load(ObjectManager $em)
    {
        $file = new EnhancementFile();
        $file->setDocumentName('animalfarm.txt');
        $file->setMimeType('text/plain');
        $file->setUploadDate(new \DateTime('now'));
        $em->persist($file);

        $file = new EnhancementFile();
        $file->setDocumentName('poobear.txt');
        $file->setMimeType('text/plain');
        $file->setUploadDate(new \DateTime('now'));
        $em->persist($file);

        $file = new EnhancementFile();
        $file->setDocumentName('threelittlepigs.txt');
        $file->setMimeType('text/plain');
        $file->setUploadDate(new \DateTime('now'));
        $em->persist($file);

        $em->flush();

        $enhFileRepo = $em->getRepository(EnhancementFile::class);
        $animalfarm = $enhFileRepo->findOneByDocumentName('animalfarm.txt');
        $threelittlepigs = $enhFileRepo->findOneByDocumentName('poobear.txt');

        $file = new OagFile();
        $file->setDocumentName('after_enrichment_activities.xml');
        $file->addEnhancingDocument($animalfarm);
        $file->addEnhancingDocument($threelittlepigs);
        $file->setUploadDate(new \DateTime('now'));
        $file->setCoved(true);
        $em->persist($file);

        $em->flush();
    }

}
