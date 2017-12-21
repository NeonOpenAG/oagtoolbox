<?php

namespace OagBundle\Command;

use OagBundle\Entity\OagFile;
use OagBundle\Service\Classifier;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OagClassifyFileCommand extends ContainerAwareCommand {
    protected function configure()
    {
        $this
            ->setName('oag:classify:file')
                ->addArgument('fileid', InputArgument::REQUIRED, 'File ID to classify');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileRepo = $this->getContainer()->get('doctrine')->getRepository(OagFile::class);

        $fileid = $input->getArgument('fileid');
        $oagfile = $fileRepo->find($fileid);
        $this->getContainer()->get('logger')->debug('Classifying ' . $oagfile->getDocumentName());

        $srvClassifier = $this->getContainer()->get(Classifier::class);
        $now = time();
        $srvClassifier->classifyOagFile($oagfile);
        $then = time();
        $this->getContainer()->get('logger')->info(
            sprintf('Classification complete on %s in %d seconds', $oagfile->getDocumentName(), $then - $now)
        );
    }

}
