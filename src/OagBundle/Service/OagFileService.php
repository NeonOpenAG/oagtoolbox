<?php

namespace OagBundle\Service;

use OagBundle\Entity\OagFile;

class OagFileService extends AbstractService
{

    /**
     * Get the base file name of an IATI OagFile on disk.
     *
     * @param OagFile $oagFile the file to get the name of
     * @return string
     */
    public function getXMLFileName(OagFile $oagFile)
    {
        $filename = $oagFile->getDocumentName();
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return $name . '.' . date("Ymd_His") . '.xml';
    }

    /**
     * Get the textual content of an OagFile on disk.
     *
     * @param OagFile $oagFile the file to get the contents of
     * @return string
     */
    public function getContents(OagFile $oagFile)
    {
        $contents = file_get_contents($this->getPath($oagFile));

        if ($contents == false) {
            throw new \Exception('OagFile contents not found at path');
        }

        return $contents;
    }

    /**
     * Get the path to an OagFile on disk.
     *
     * @param OagFile $oagFile the file to get the path of
     * @return string
     */
    public function getPath(OagFile $oagFile)
    {
        if ($oagFile->isCoved()) {
            $path = $this->getContainer()->getParameter('oagxml_directory');
        } else {
            $path = $this->getContainer()->getParameter('oagfiles_directory');
        }
        $path .= '/' . $oagFile->getDocumentName();
        return $path;
    }

    /**
     * Set the textual content of an OagFile on disk.
     *
     * @param OagFile $oagFile the file to set the contents of
     * @param string $contents the new contents of the file
     * @return depends on file_put_contents
     */
    public function setContents(OagFile $oagFile, $contents)
    {
        // TODO check for/log errors
        return file_put_contents($this->getPath($oagFile), $contents);
    }

    /**
     * Gets whetheer the file has been fully improved. Fully improved is
     * currently defined as both classified and geocoded.
     *
     * @param OagFile $oagFile
     * @return boolean
     */
    public function hasBeenImproved(OagFile $oagFile)
    {
        return $this->hasBeenClassified($oagFile) && $this->hasBeenGeocoded($oagFile);
    }

    /**
     * Gets whether the file has been classified. Classification is currently
     * defined as a single net change to the tags of the IATI file.
     *
     * @deprecated Use stats to evaluate now
     *
     * @param OagFile $oagFile
     * @return boolean
     */
    public function hasBeenClassified(OagFile $file)
    {
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
     * @deprecated Use stats to evaluate now
     *
     * @param OagFile $oagFile
     * @return boolean
     */
    public function hasBeenGeocoded(OagFile $file)
    {
        foreach ($file->getChanges() as $change) {
            if (count($change->getAddedGeolocs()) > 0 || count($change->getRemovedGeolocs()) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the most recent file uploaded to the toolbox.
     *
     * @return OagFile|null null if no files are uploaded
     */
    public function getMostRecent()
    {
        $oagFileRepo = $this->getContainer()->get('doctrine')->getRepository(OagFile::class);
        $files = $oagFileRepo->findAll();

        if (count($files) === 0) {
            return null;
        }

        usort($files, function ($a, $b) {
            if ($a->getUploadDate() < $b->getUploadDate()) {
                // $a happened before $b
                return -1;
//            } elseif ($a->getTimestamp() > $b->getTimestamp()) {
//                // $b happened before $a
//                return 1;
            }
            return 0;
        });

        return end($files);
    }

}
