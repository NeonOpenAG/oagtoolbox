<?php

namespace OagBundle\Service;

use OagBundle\Entity\Change;
use OagBundle\Entity\OagFile;

class OagFileService extends AbstractService {

    /**
     * Get the base file name of an IATI OagFile on disk.
     *
     * @param OagFile $oagFile the file to get the name of
     * @return string
     */
    public function getXMLFileName(OagFile $oagFile) {
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
        if ($oagFile->isCoved()) {
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
    public function getContents(OagFile $oagFile) {
        $contents = file_get_contents($this->getPath($oagFile));

        if ($contents == false) {
            throw new \Exception('OagFile contents not found at path');
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
    public function setContents(OagFile $oagFile, $contents) {
        // TODO check for/log errors
        return file_put_contents($this->getPath($oagFile), $contents);
    }

    /**
     * Gets whether the file has been classified. Classification is currently
     * defined as a single net change to the tags of the IATI file.
     *
     * @param OagFile $oagFile
     * @return boolean
     */
    public function hasBeenClassified(OagFile $file) {
        foreach ($file->getChanges() as $change) {
            if (count($change->getAddedTags()) > 0 || count($change->getRemovedTags()) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets whether the file has been geocoded. Geocoding is currently defined
     * as a single net change to the locations of the IATI file.
     *
     * @param OagFile $oagFile
     * @return boolean
     */
    public function hasBeenGeocoded(OagFile $file) {
        foreach ($file->getChanges() as $change) {
            if (count($change->getAddedGeolocs()) > 0 || count($change->getRemovedGeolocs()) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets whetheer the file has been fully improved. Fully improved is
     * currently defined as both classified and geocoded.
     *
     * @param OagFile $oagFile
     * @return boolean
     */
    public function hasBeenImproved(OagFile $oagFile) {
        return $this->hasBeenClassified($oagFile) && $this->hasBeenGeocoded($oagFile);
    }

}
