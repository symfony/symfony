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
     * Supported formats for export
     * 
     * @var array
     */
    private $formatters = array();
        
    /**
     * Add a formatter to the writer
     * @param string $format The format of the loader
     * @param FormatterInterface $formatter 
     */
    public function addFormatter($format, FormatterInterface $formatter)
    {
        $this->formatters[$format] = $formatter;
    }
    
    /**
     * Obtains the list of supported formats
     * @return array 
     */
    public function getFormats()
    {
        return array_keys($this->formatters);
    }
    
    public function writeTranslations(MessageCatalogue $catalogue, $path, $format)
    {
        // get the right formatter
        $formatter = $this->formatters[$format];

        // save
        foreach ($catalogue->getDomains() as $domain) {
            $file = $domain.'.'.$catalogue->getLocale().'.'.$format;
            if (file_exists($path . $file)) {
                copy($path.$file, $path.'~'.$file.'.bak');
            }
            file_put_contents($path.'/'.$file, $formatter->format($catalogue->all($domain)));
        }
    }
}
