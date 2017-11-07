<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OagBundle\Service\TextExtractor;

use OagBundle\Service\EnhancementFileService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Description of TextifyService
 *
 * @author tobias
 */
class TextifyService
{

    private $container;

    /**
     * Gets the raw text content of an EnhancementFile.
     *
     * @param EnhancementFile $enhFile the file to get the text of
     * @return string
     */
    public function stripEnhancementFile($enhancementFile)
    {
        $srvEnhancementFile = $this->container->get(EnhancementFileService::class);
        $path = $srvEnhancementFile->getPath($enhancementFile);
//        $mimetype = mime_content_type($path);
//        $this->container->get('logger')->info(sprintf('File %s mime type is %s', $path, $mimetype));
        $mimetype = $enhancementFile->getMimeType();

        switch ($mimetype) {
            case 'application/pdf':
            case 'application/pdf':
            case 'application/x-pdf':
            case 'application/acrobat':
            case 'applications/vnd.pdf':
            case 'text/pdf':
            case 'text/x-pdf':
                // pdf
                $this->container->get('logger')->debug('Detected as PDF');
                $decoder = new PDFExtractor();
                $decoder->setFilename($path);
                $decoder->decode();
                return $decoder->output();
                break;
            case 'application/txt':
            case 'browser/internal':
            case 'text/anytext':
            case 'widetext/plain':
            case 'widetext/paragraph':
            case 'text/plain':
            case 'text/html':
            case 'application/xml':
                // txt or xml
                $this->container->get('logger')->debug('Detected as XML');
                return file_get_contents($path);
                break;
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/msword':
            case 'application/doc':
            case 'application/zip':
                // docx
                $this->container->get('logger')->debug('Detected as MS doc');
                // phpword can't save to txt directly
                $tmpRtfFile = tempnam(sys_get_temp_dir(), basename($sourceFile));
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($path, 'Word2007');
                $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'RTF');
                $objWriter->save($tmpRtfFile);
                // Now let the switch fall through to decode rtf
                $path = $tmpRtfFile;
            case 'application/rtf':
            case 'application/x-rtf':
            case 'text/richtext':
            case 'text/rtf':
                // rtf
                $this->container->get('logger')->debug('Detected as RTF');
                $decoder = new RTFExtractor();
                $decoder->setFilename($path);
                $decoder->decode();
                return $decoder->output();
                break;
        }
        return false;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

}
