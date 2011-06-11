<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Write translation messages to translation files
 */
class FileTranslationWriter
{    
    /**
     * The container
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;
    
    function __construct($container) {
        $this->container = $container;
    }
    
    public function writeTranslations(MessageCatalogue $catalogue, $path, $format){
        // get the right formatter
        $formatter = $this->container->get('twig.translation.formatter.' . $format);

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
