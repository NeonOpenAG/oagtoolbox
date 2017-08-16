<?php

namespace OagBundle\Service;

class OagFileService extends AbstractService {

    public function getXMLFileName($oagFile) {
        $filename = $oagFile->getDocumentName();
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return $name . '.' . date("Ymd_His") . '.xml';
    }

}
