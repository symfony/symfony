<?php

namespace Symfony\Component\Translation\Extractor;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Extract translation messages from template files
 */
class TranslationExtractor
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
     * @param ExtractorInterface $extractor 
     */
    public function addExtractor($format, ExtractorInterface $extractor)
    {
        $this->extractors[$format] = $extractor;
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
        foreach ($this->extractors as $extractor) {
            $extractor->load($directory, $catalogue);
        }
    }
}
