<?php

namespace OagBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use DOMDocument;
use DOMXPath;

class OagFetchIatiCommand extends ContainerAwareCommand
{
    protected $targetdir = false;
    protected $page = 1;
    protected $noclobber = false;

    protected function configure()
    {
        $this
            ->setName('oag:fetch:iati')
            ->setDescription('Get a pge of IATI xml documents')
                ->addArgument('page', InputArgument::OPTIONAL, 'Page number')
                ->addOption('noclobber', 'c', InputOption::VALUE_NONE, 'If set skip existing documents.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->targetdir = $this->getContainer()->get('kernel')->getProjectDir() . '/xmlcache'; // Should use container -> get root

        if ($input->getArgument('page')) {
            $this->page = $input->getArgument('page');
        }

        if ($input->getOption('noclobber')) {
            $this->noclobber = $input->getOption('noclobber');
        }

        if (!is_dir($this->targetdir)) {
            mkdir($this->targetdir, 0775, true);
        }

        $registryUrl = "https://www.iatiregistry.org/dataset?page=" . $this->page;
        $output->writeln("= Fetching " . $registryUrl);
        $page = file_get_contents($registryUrl);
        if (!$page) {
            $output->writeln("! " . $registryUrl . " note found.");
        }
        $dom = new DOMDocument();
        @$dom->loadHTML($page);

        $xpath = new DOMXpath($dom);
        $result = $xpath->query("//*[contains(@class, 'dataset-content')]");
        if ($result && $result->length > 0) {
            foreach ($result as $domElement) {
                $_result = $xpath->query('./p/a', $domElement);
                if ($_result && $_result->length > 1) {
                    $url = $_result->item(1)->getAttribute('href');
                    $filename = preg_replace('/[^a-zA-Z0-9]+/', '-', $url);
                    $output->writeln($this->fetchXml($filename, $url));
                }
            }
        } else {
            $output->writeln("- No DATA");
        }
    }

    private function fetchXml($filename, $url) {
        $target = $this->targetdir . '/' . $filename . '.xml';

        if ($this->noclobber && file_exists($target)) {
            return 'o Skipping (noclobber) ' . $filename;
        }

        $data = @file_get_contents($url);
        $fh = fopen($target, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return sprintf('+ Wrote %d bytes of data from %s to %s', strlen($data), $url, $target);
    }
}
