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


//        $output_array = [];
//        $re = '/[^\\\\\/]+(?=\.[\w]+$)|[^\\\\\/]+$/';
//        preg_match($re, $filename, $output_array, PREG_OFFSET_CAPTURE, 0);
//
//        if (count($output_array)) {
//            $rawname = $output_array[0][0];
//        } else {
//            $rawname = uniqid('file');
//        }
    }

}
