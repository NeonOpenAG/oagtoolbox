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
        $contents = file_get_contents($this->getPath($oagFile));

        if ($contents === false) {
            throw \Exception('OagFile contents not found at path');
        }

        return $contents;
    }

    public function setContents($oagFile, $contents) {
        // TODO check for/log errors
        return file_put_contents($this->getPath($oagFile), $contents);
    }

}
