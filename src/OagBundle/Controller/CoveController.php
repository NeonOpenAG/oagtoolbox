<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use OagBundle\Service\Cove;
use OagBundle\Entity\OagFile;
use OagBundle\Service\OagFileService;

/**
 * @Route("/cove")
 * @Template
 */
class CoveController extends Controller {

    /**
     * @Route("/{fileid}", requirements={"fileid": "\d+"})
     * @Template
     */
    public function indexAction($fileid) {
        $messages = [];
        $cove = $this->get(Cove::class);
        $srvOagFile = $this->get(OagFileService::class);

        $repository = $this->getDoctrine()->getRepository(OagFile::class);
        $oagfile = $repository->find($fileid);
        if (!$oagfile) {
            throw $this->createNotFoundException(sprintf('The document %d does not exist', $fileid));
        }
        $this->get('logger')->debug(sprintf('Processing %s using CoVE', $oagfile->getDocumentName()));
        // TODO - for bigger files we might need send as Uri
        $path = $srvOagFile->getPath($oagfile);
        $contents = file_get_contents($path);
        $json = $cove->processString($contents);

        $xml = $json['xml'];
        $xmldir = $this->getParameter('oagxml_directory');
        if (!is_dir($xmldir)) {
            mkdir($xmldir, 0755, true);
        }
        $filename = $srvOagFile->getXMLFileName($oagfile);
        $xmlfile = $xmldir . '/' . $srvOagFile->getXMLFileName($oagfile);
        file_put_contents($xmlfile, $xml);

        $err = $json['err'] ?? '';
        $status = $json['status'] ?? '';

        $pretty_json = json_encode($json, JSON_PRETTY_PRINT);
        return array(
            'messages' => $messages,
            'path' => $oagfile->getDocumentName(),
            'xml' => $xmlfile,
            'err' => $err,
            'status' => $status,
            'id' => $oagfile->getId(),
        );
    }

}
