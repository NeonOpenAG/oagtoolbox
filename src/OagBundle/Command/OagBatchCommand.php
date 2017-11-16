<?php

namespace OagBundle\Command;

use OagBundle\Entity\OagFile;
use OagBundle\Service\Classifier;
use OagBundle\Service\Cove;
use OagBundle\Service\Geocoder;
use OagBundle\Service\OagFileService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OagBatchCommand extends ContainerAwareCommand
{

    private $output;
    private $srvCove;
    private $srvGeocoder;
    private $srvClassifier;
    private $srvOagFile;
    private $em;

    /**
     * @return OutputInterface
     */
    function getOutput()
    {
        return $this->output;
    }

    /**
     * @return OagFileService
     */
    function getSrvOagFile()
    {
        return $this->srvOagFile;
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    function getEm()
    {
        return $this->em;
    }

    protected function configure()
    {
        $this
            ->setName('oag:batch')
            ->setDescription("List files/URI's to process as IATI imports. ./bin/console oag:batch --env dev https://raw.githubusercontent.com/devgateway/geocoder-ie/master/example.xml
")
            ->addArgument('files', InputArgument::IS_ARRAY, 'Specify files/URIs!')
            ->addOption('destination', '-d', InputOption::VALUE_NONE, 'Output festination')
            ->addOption('skip-geo', '-g', InputOption::VALUE_NONE, 'Skip Geocoder')
            ->addOption('skip-cla', '-c', InputOption::VALUE_NONE, 'Skip Classifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->srvCove = $this->getContainer()->get(Cove::class);
        $this->srvGeocoder = $this->getContainer()->get(Geocoder::class);
        $this->srvClassifier = $this->getContainer()->get(Classifier::class);
        $this->srvOagFile = $this->getContainer()->get(OagFileService::class);
        $this->em = $this->getContainer()->get('doctrine')->getManager();


        $files = $input->getArgument('files');

        $skipGeo = $input->getOption('skip-geo') ?? false;
        $skipCla = $input->getOption('skip-cla') ?? false;

        $outputRoot = getcwd() . '/output';
        if ($input->getOption('destination')) {
            $outputRoot = rtrim('/', $input->getOption('destination') . '/output');
        }

        $fh = fopen($outputRoot . '/time.log', 'a');
        fwrite($fh, '======== ' . date('Ymd H:i:s') . ' ========');
        fclose($fh);

        if (!is_dir($outputRoot)) {
            mkdir($outputRoot, 0775, true);
        }
        $this->feedback('Output root set to ' . $outputRoot);

        foreach ($files as $file) {
            $this->feedback("\n-= " . $file . ' =-');

            // TODO convert to regex
            $safename = str_replace("/", "_", str_replace('.', '_', $file));
            $outputDir = $outputRoot . '/' . $safename;
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0775, true);
                $this->feedback('Output dir created, ' . $outputDir);
            }

            $data = file_get_contents($file);
            $oagFileName = $outputDir . '/' . basename($file);
            $fh = fopen($oagFileName, 'w');
            fwrite($fh, $data);
            fclose($fh);
            $this->feedback(sprintf('Wrote %d bytes to %s', strlen($data), $oagFileName));

            // Create an Oag file, it's easier if we have it as an oag file.
            $oagFile = $this->oagFile($oagFileName);
            // cove it
            $now = time();
            $success = $this->cove($oagFile);
            $then = time();

            $covefilename = $outputDir . '/cove.json';
            $fh = fopen($covefilename, 'w');
            fwrite($fh, json_encode($this->getSrvCove()->getJson(), JSON_PRETTY_PRINT));
            fclose($fh);
            $this->feedback(sprintf('CoVE Json written to %d,', $covefilename));
            if ($success) {
                $msg = sprintf('Cove complete on %s in %d seconds', $oagFile->getDocumentName(), $then - $now);
            } else {
                $msg = sprintf('Cove failed on %s in %d seconds', $oagFile->getDocumentName(), $then - $now);
            }

            $this->feedback($msg);
            $fh = fopen($outputRoot . '/time.log', 'a');
            fwrite($fh, $msg);
            fclose($fh);

            if (!$success) {
                continue;
            }

            if (!$skipGeo) {
                // geocode it
                $now = time();
                $this->geocode($oagFile);
                $then = time();
                $this->feedback(sprintf('Geocoding complete on %s in %d seconds', $oagFile->getDocumentName(), $then - $now));
                $geoSugestions = $oagFile->getGeolocations();
                $fh = fopen($outputDir . '/geosuggestions.txt', 'w');
                foreach ($geoSugestions as $geo) {
                    fwrite($geo->toString());
                }
                fclose($fh);
            }

            if (!$skipCla) {
                // classify it
                $now = time();
                $this->classify($oagFile);
                $then = time();
                $this->feedback(sprintf('Classifier complete on %s in %d seconds', $oagFile->getDocumentName(), $then - $now));
                $gatSugestions = $oagFile->getSuggestedTags();
                $fh = fopen($outputDir . '/tagsuggestions.txt', 'w');
                foreach ($gatSugestions as $tag) {
                    fwrite($fh, $tag->toString());
                }
                fclose($fh);
            }
        }

        $this->feedback("\nCommand result.");
    }

    private function feedback($msg)
    {
        $this->output->writeln($msg);
    }

    private function oagFile($oagFileName)
    {
        $oagFullPath = $this->getContainer()->getParameter('oagfiles_directory') . '/' . basename($oagFileName);
        copy($oagFileName, $oagFullPath);

        $documentName = pathinfo($oagFullPath, PATHINFO_BASENAME);

        $oagFile = new OagFile();
        $oagFile->setDocumentName($documentName);
        $oagFile->setUploadDate(new \DateTime('now'));

        $this->em->persist($oagFile);
        $this->em->flush();

        $this->feedback(sprintf('Created new oag file %s, ID %s', $documentName, $oagFile->getId()));

        return $oagFile;
    }

    private function cove(OagFile $file)
    {
        return $this->getSrvCove()->validateOagFile($file);
    }

    /**
     * @return Cove
     */
    function getSrvCove()
    {
        return $this->srvCove;
    }

    private function geocode(OagFile $file)
    {
        return $this->getSrvGeocoder()->geocodeOagFile($file);
    }

    /**
     * @return Geocoder
     */
    function getSrvGeocoder()
    {
        return $this->srvGeocoder;
    }

    private function classify(OagFile $file)
    {
        return $this->getSrvClassifier()->classifyOagFile($file);
    }

    /**
     * @return Classifier
     */
    function getSrvClassifier()
    {
        return $this->srvClassifier;
    }

}
