<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Service\IATI;
use OagBundle\Service\Cove;
use OagBundle\Service\OagFileService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/cove")
 * @Template
 */
class CoveController extends Controller {

    /**
     * Process on OagFile with CoVE.
     *
     * @Route("/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function oagFileAction(Request $request, OagFile $file) {
        $cove = $this->get(Cove::class);
        $srvOagFile = $this->get(OagFileService::class);
        $srvIATI = $this->get(IATI::class);

        $this->get('logger')->debug(sprintf('Processing %s using CoVE', $file->getDocumentName()));
        // TODO - for bigger files we might need send as Uri
        $contents = $srvOagFile->getContents($file);
        $json = $cove->processString($contents);

        $err = array_filter($json['err'] ?? array());
        $status = $json['status'];

        if ($status === 0) {
            // CoVE claims to have processed the XML successfully
            $xml = $json['xml'];
            if ($srvIATI->parseXML($xml)) {
                // CoVE actually has returned valid XML
                $xmldir = $this->getParameter('oagxml_directory');
                if (!is_dir($xmldir)) {
                    mkdir($xmldir, 0755, true);
                }

                $filename = $srvOagFile->getXMLFileName($file);
                $xmlfile = $xmldir . '/' . $filename;
                if (!file_put_contents($xmlfile, $xml)) {
                    $this->get('session')->getFlashBag()->add('error', 'Unable to create XML file.');
                    $this->get('logger')->debug(sprintf('Unable to create XML file: %s', $xmlfile));
                    return $this->redirectToRoute('oag_default_index');
                }
                // else
                if ($this->getParameter('unlink_files')) {
                    unlink($path);
                }

                $file->setDocumentName($filename);
                $file->setFileType(OagFile::OAGFILE_IATI_DOCUMENT);
                $file->setMimeType('application/xml');
                $em = $this->getDoctrine()->getManager();
                $em->persist($file);
                $em->flush();
                $this->get('session')->getFlashBag()->add('info', 'IATI File created/Updated\; ' . $xmlfile);
            } else {
                $this->get('session')->getFlashBag()->add('error', 'CoVE returned data that was not XML.');
            }
        } else {
            // CoVE returned with an error, spit out stderr
            foreach ($err as $line) {
                $this->get('session')->getFlashBag()->add('error', $line);
            }
        }

        return $this->redirectToRoute('oag_default_index');
    }

}
