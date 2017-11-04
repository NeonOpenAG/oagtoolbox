<?php

namespace OagBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OagBundle\Service\Docker;

class OagFetchimagesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('oag:fetchimages')
            ->setDescription('Pulls the OAG docker images onto the host machine')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $reponame = $this->getContainer()->getParameter('docker_reponame');
        $dockernames = $this->getContainer()->getParameter('docker_names');

        $srvClassifier = $this->getContainer()->get(Docker::class);

        foreach ($dockernames as $name) {
            $imagename = $reponame . '/' . $name;
            $output->write('Pulling ' . $imagename);
            $srvClassifier->pullImage($imagename);
            $output->writeln('. Done');
        }
    }

}
