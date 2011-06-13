<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Formatter\FormatterInterface;

/**
 * Write translation messages to translation files
 */
class FileTranslationWriter
{    
    /**
     * Supported formats for import
     * 
     * @var array
     */
    private $formatters;
        
    /**
     * Add a formatter to the translation extractor
     * @param string $format The format of the loader
     * @param FormatterInterface $formatter 
     */
    public function addLoader($format, FormatterInterface $formatter){
        $this->formatters[$format] = $formatters;
    }
    
    public function writeTranslations(MessageCatalogue $catalogue, $path, $format)
    {
        // get the right formatter
        $formatter = $this->formatters[$format];

        // save
        foreach ($catalogue->getDomains() as $domain) {
            $file = $domain . '.' . $catalogue->getLocale() . '.' . $format;
            if (file_exists($path . $file)) {
                copy($path . $file, $path . '~' . $file . '.bak');
            }
            file_put_contents($path . '/' . $file, $formatter->format($catalogue->all($domain)));
        }
    }
}
