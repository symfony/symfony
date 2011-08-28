<?php

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Loader\LoaderInterface;

/**
 * Load translation messages from translation files
 */
class TranslationLoader
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
     * Load translation messages from a directory to the catalogue
     * 
     * @param string $directory the directory to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    public function loadMessages($directory, MessageCatalogue $catalogue)
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
