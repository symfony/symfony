<?php

namespace Symfony\Component\Translation\Extractor;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Interface implemented by all extractors
 */
interface ExtractorInterface
{
    /**
     * Extract translation messages from a template directory to the catalogue
     * 
     * @param string $directory the path to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    function load($directory, MessageCatalogue $catalogue);
    
    /**
     * Set the prefix that should be used for new found messages
     * 
     * @param type $prefix The prefix
     */
    public function setPrefix($prefix);
}
