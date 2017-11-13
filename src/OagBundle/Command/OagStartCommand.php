<?php

namespace OagBundle\Command;

use OagBundle\Service\Docker;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class OagStartCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('oag:start')
            ->setDescription('Starts an underlying docker.')
            ->addArgument('dockers', InputArgument::IS_ARRAY + InputArgument::OPTIONAL, 'List of dockers to start, if none, all are started');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dockers = $input->getArgument('dockers');

        if ($this->getContainer()->get('kernel')->getEnvironment() == "test") {
            // Skip this in the test env
            return;
        }
        $srvDocker = $this->getContainer()->get(Docker::class);
        // $containers = $srvDocker->fetchImages();
        $containers = $srvDocker->listContainers();

        foreach ($dockers as $docker) {
            $name = 'openag_' . $docker;
            $this->getContainer()->get('logger')->info($name);
            if (array_key_exists($name, $containers)) {
                if ($containers['openag_' . $docker]['status'] == 'running') {
                    $output->writeln($name . ' is running.');
                    continue;
                }
                $id = $containers['openag_' . $docker]['container_id'];
            } else {
                $output->writeln($docker . ' not started');
                // This doesn't work I can't gert the return value back out
                // $func = 'create' . ucfirst($container);
                // $container = $srvDocker->$func;
                // call_user_func(array($srvDocker, $func), $_container);
                switch ($docker) {
                    case 'cove:live':
                        $_container = $srvDocker->createCove();
                        break;
                    case 'dportal:live':
                        $_container = $srvDocker->createDportal();
                        break;
                    case 'nerserver:live':
                        $_container = $srvDocker->createNerserver();
                        break;
                    case 'geocoder:live':
                        $_container = $srvDocker->createGeocode();
                        break;
                }
                $id = $_container['Id'] ?? false;
            }

            if ($id) {
                $srvDocker->startContainer($id);
            }
        }
    }

}
