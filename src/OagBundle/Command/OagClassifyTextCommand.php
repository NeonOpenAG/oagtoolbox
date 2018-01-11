<?php

namespace OagBundle\Command;

use OagBundle\Service\Classifier;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OagClassifyTextCommand extends ContainerAwareCommand {

    protected function configure() {
        $this
            ->setName('oag:classify:text')
                ->addArgument('file', InputArgument::REQUIRED, 'Text file to classify');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $srvClassifier = $this->getContainer()->get(Classifier::class);
        $file = $input->getArgument('file');
        $contents = file_get_contents($file);

        $now = time();
        $data = $srvClassifier->processString($contents);
        $then = time();
        $output->writeln(sprintf('Classification complete in %d seconds', $then - $now));
        $output->writeln(print_r($data, true));
    }

}
