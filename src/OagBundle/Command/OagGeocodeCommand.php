<?php

namespace OagBundle\Command;

use OagBundle\Entity\OagFile;
use OagBundle\Service\Geocoder;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OagGeocodeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('oag:geocode:file')
                ->addArgument('fileid', InputArgument::REQUIRED, 'File ID to geocode');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileRepo = $this->getContainer()->get('doctrine')->getRepository(OagFile::class);

        $fileid = $input->getArgument('fileid');
        $oagfile = $fileRepo->findOneById($fileid);
        $this->getContainer()->get('logger')->debug('Geocoding ' . $oagfile->getDocumentName());

        $srvGeocode = $this->getContainer()->get(Geocoder::class);
        $now = time();
        $srvGeocode->geocodeOagFile($oagfile);
        $then = time();
        $this->getContainer()->get('logger')->info(
            sprintf('Geocoding complete on %s in %d seconds', $oagfile->getDocumentName(), $then - $now)
        );
    }

}
