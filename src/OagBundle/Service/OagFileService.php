<?php

namespace OagBundle\Service;

use OagBundle\Entity\OagFile;

class OagFileService extends AbstractService {

    /**
     * Get the base file name of an IATI OagFile on disk.
     *
     * @param OagFile $oagFile the file to get the name of
     * @return string
     */
    public function getXMLFileName($oagFile) {
        $filename = $oagFile->getDocumentName();
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return $name . '.' . date("Ymd_His") . '.xml';
    }

    /**
     * Get the path to an OagFile on disk.
     *
     * @param OagFile $oagFile the file to get the path of
     * @return string
     */
    public function getPath(OagFile $oagFile) {
        if ($oagFile->getFileType() == OagFile::OAGFILE_IATI_DOCUMENT) {
            $path = $this->getContainer()->getParameter('oagxml_directory');
        } else {
            $path = $this->getContainer()->getParameter('oagfiles_directory');
        }
        $path .= '/' . $oagFile->getDocumentName();
        return $path;
    }

    /**
     * Get the textual content of an OagFile on disk.
     *
     * @param OagFile $oagFile the file to get the contents of
     * @return string
     */
    public function getContents($oagFile) {
        $contents = file_get_contents($this->getPath($oagFile));

        if ($contents === false) {
            throw \Exception('OagFile contents not found at path');
        }

        return $contents;
    }

    /**
     * Set the textual content of an OagFile on disk.
     *
     * @param OagFile $oagFile the file to set the contents of
     * @param string $contents the new contents of the file
     * @return depends on file_put_contents
     */
    public function setContents($oagFile, $contents) {
        // TODO check for/log errors
        return file_put_contents($this->getPath($oagFile), $contents);
    }

}
