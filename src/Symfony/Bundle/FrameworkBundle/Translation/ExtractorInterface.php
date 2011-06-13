<?php

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Interface implemented by all extractors
 */
interface ExtractorInterface {
    /**
     * Extract translation messages from a template file to the catalogue
     * 
     * @param string $file the path to the template file to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    function load($file, MessageCatalogue $catalogue);
    
    /**
     * Set the prefix that should be used for new found messages
     * 
     * @param type $prefix The prefix
     */
    public function setPrefix($prefix);
}
