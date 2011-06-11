<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Interface for translaton extractor
 */
interface TranslationExtractorInterface
{
    /**
     * Extract translation messages to the catalogue
     * 
     * @param string $directory the directory to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    function extractMessages($directory, MessageCatalogue $catalogue);
}
