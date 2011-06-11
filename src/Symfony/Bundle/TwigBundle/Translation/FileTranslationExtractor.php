<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Extract translation messages from translation files
 */
class FileTranslationExtractor implements TranslationExtractorInterface
{
    /**
     * Supported formats for import
     * 
     * @var array
     */
    private $supportedLoaders = array('yml', 'xliff', 'php');
    
    /**
     * The container
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;
    
    function __construct($container) {
        $this->container = $container;
    }
    
    /**
     * {@inheritDoc}
     */
    public function extractMessages($directory, MessageCatalogue $catalogue) {
        foreach($this->supportedLoaders as $format) {
            // load any existing translation files
            $finder = new Finder();
            $files = $finder->files()->name('*.' . $catalogue->getLocale() . $format)->in($directory);
            foreach ($files as $file) {
                $domain = substr($file->getFileName(), 0, strrpos($file->getFileName(), $input->getArgument('locale') . $attributes[0]['alias']) - 1);
                $loader = $this->container->get('translation.loader.' . $format);
                $catalogue->addCatalogue($loader->load($file->getPathname(), $input->getArgument('locale'), $domain));
            }
        }
    }
}
