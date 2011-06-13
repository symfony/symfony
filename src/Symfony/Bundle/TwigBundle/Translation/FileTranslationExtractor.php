<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Loader\LoaderInterface;

/**
 * Extract translation messages from translation files
 */
class FileTranslationExtractor implements TranslationExtractorInterface
{
    /**
     * Loaders used for import
     * 
     * @var array
     */
    private $loaders = array();
        
    /**
     * Add a loader to the translation extractor
     * @param string $format The format of the loader
     * @param LoaderInterface $loader 
     */
    public function addLoader($format, LoaderInterface $loader)
    {
        $this->loaders[$format] = $loader;
    }
    
    /**
     * {@inheritDoc}
     */
    public function extractMessages($directory, MessageCatalogue $catalogue)
    {
        foreach($this->loaders as $format => $loader) {
            // load any existing translation files
            $finder = new Finder();
            $files = $finder->files()->name('*.'.$catalogue->getLocale().$format)->in($directory);
            foreach ($files as $file) {
                $domain = substr($file->getFileName(), 0, strrpos($file->getFileName(), $input->getArgument('locale').$format) - 1);
                $catalogue->addCatalogue($loader->load($file->getPathname(), $input->getArgument('locale'), $domain));
            }
        }
    }
}
