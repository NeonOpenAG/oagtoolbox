<?php

namespace OagBundle\Service;

class OagFileService extends AbstractService {

    public function getXMLFileName($oagFile) {
        $filename = $oagFile->getDocumentName();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext != 'xml') {
            $filename .= '.xml';
        }
        return $filename;
    }

    public function getPath($oagFile) {
        $path = $this->getContainer()->getParameter('oagfiles_directory');
        $path .= '/' . $oagFile->getDocumentName();
        return $path;
    }

    public function getContents($oagFile) {
        return file_get_contents($this->getPath($oagFile));
    }

    public function setContents($oagFile, $contents) {
        return file_put_contents($this->getPath($oagFile), $contents);
    }

}
