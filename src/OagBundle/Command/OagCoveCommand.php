<?php

namespace OagBundle\Command;

use OagBundle\Entity\OagFile;
use OagBundle\Service\Cove;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OagCoveCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('oag:cove')
            ->addArgument('fileid', InputArgument::REQUIRED, 'File ID to geocode');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileRepo = $this->getContainer()->get('doctrine')->getRepository(OagFile::class);

        $fileid = $input->getArgument('fileid');
        $oagfile = $fileRepo->findOneById($fileid);
        $this->getContainer()->get('logger')->debug('Cove ' . $oagfile->getDocumentName());

        $srvCove = $this->getContainer()->get(Cove::class);
        $now = time();
        $srvCove->validateOagFile($oagfile);
        $then = time();
        $this->getContainer()->get('logger')->info(
            sprintf('Cove complete on %s in %d seconds', $oagfile->getDocumentName(), $then - $now)
        );
    }

}
