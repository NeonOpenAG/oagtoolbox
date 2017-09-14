<?php

namespace OagBundle\Service;

use OagBundle\Entity\Change;
use OagBundle\Entity\EnhancementFile;

class EnhancementFileService extends AbstractService {

    /**
     * Get the path to an EnhancementFile on disk.
     *
     * @param EnhancementFile $enhFile the file to get the path of
     * @return string
     */
    public function getPath(EnhancementFile $enhFile) {
        $path = $this->getContainer()->getParameter('oagfiles_directory');
        $path .= '/' . $enhFile->getDocumentName();
        return $path;
    }

    /**
     * Get the textual content of an EnhancementFile on disk.
     *
     * @param EnhancementFile $enhFile the file to get the contents of
     * @return string
     */
    public function getContents(EnhancementFile $enhFile) {
        $contents = file_get_contents($this->getPath($enhFile));

        if ($contents == false) {
            throw new \Exception('EnhancementFile contents not found at path');
        }

        return $contents;
    }

    /**
     * Set the textual content of an EnhancementFile on disk.
     *
     * @param EnhancementFile $enhFile the file to set the contents of
     * @param string $contents the new contents of the file
     * @return depends on file_put_contents
     */
    public function setContents(EnhancementFile $enhFile, $contents) {
        // TODO check for/log errors
        return file_put_contents($this->getPath($enhFile), $contents);
    }

}
