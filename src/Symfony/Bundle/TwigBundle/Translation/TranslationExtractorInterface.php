<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Interface for translaton extractor
 */
interface TranslationExtractorInterface {
    /**
     * Extracto transaltion messages to the catalogue
     * @param string the directory to look into
     * @param Symfony\Component\Translation\MessageCatalogue the catalogue
     */
    public function extractMessages($directory, MessageCatalogue $catalogue);
}
