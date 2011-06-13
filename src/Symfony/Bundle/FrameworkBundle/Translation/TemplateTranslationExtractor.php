<?php

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Extract translation messages from translation files
 */
class TemplateTranslationExtractor
{
    /**
     * Extractors
     * 
     * @var array
     */
    private $extractors = array();

    /**
     * Add a loader to the translation extractor
     * @param string $format The format of the loader
     * @param ExtractorInterface $extactor 
     */
    public function addExtractor($format, ExtractorInterface $extactor)
    {
        $this->extractors[$format] = $extactor;
    }

    /**
     * Set prefix applied for new found messages
     */
    public function setPrefix($prefix)
    {
        foreach($this->extractors as $extractor){
            $extractor->setPrefix($prefix);
        }
    }
    
    /**
     * Extract translation messages from a directory to the catalogue
     * 
     * @param string $directory the directory to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    public function extractMessages($directory, MessageCatalogue $catalogue)
    {
        foreach($this->extractors as $format => $extactor) {
            // load any existing translation files
            $finder = new Finder();
            $files = $finder->files()->name('*.'.$format)->in($directory);
            foreach ($files as $file) {
                $extactor->load($file, $catalogue);
            }
        }
    }
}
