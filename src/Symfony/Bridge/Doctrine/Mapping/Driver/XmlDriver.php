<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Mapping\Driver;

use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\Driver\XmlDriver as BaseXmlDriver;

/**
 * XmlDriver that additionally looks for mapping information in a global file.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XmlDriver extends BaseXmlDriver
{
    protected $_prefixes = array();
    protected $_globalBasename;
    protected $_classCache;
    protected $_fileExtension = '.orm.xml';

    public function setGlobalBasename($file)
    {
        $this->_globalBasename = $file;
    }

    public function getGlobalBasename()
    {
        return $this->_globalBasename;
    }

    public function setNamespacePrefixes($prefixes)
    {
        $this->_prefixes = $prefixes;
    }

    public function getNamespacePrefixes()
    {
        return $this->_prefixes;
    }

    public function isTransient($className)
    {
        if (null === $this->_classCache) {
            $this->initialize();
        }

        // The mapping is defined in the global mapping file
        if (isset($this->_classCache[$className])) {
            return false;
        }

        try {
            $this->_findMappingFile($className);

            return false;
        } catch (MappingException $e) {
            return true;
        }
    }

    public function getAllClassNames()
    {
        if (null === $this->_classCache) {
            $this->initialize();
        }

        $classes = array();

        if ($this->_paths) {
            foreach ((array) $this->_paths as $path) {
                if (!is_dir($path)) {
                    throw MappingException::fileMappingDriversRequireConfiguredDirectoryPath($path);
                }

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    $fileName = $file->getBasename($this->_fileExtension);

                    if ($fileName == $file->getBasename() || $fileName == $this->_globalBasename) {
                        continue;
                    }

                    // NOTE: All files found here means classes are not transient!
                    if (isset($this->_prefixes[$path])) {
                        $classes[] = $this->_prefixes[$path].'\\'.str_replace('.', '\\', $fileName);
                    } else {
                        $classes[] = str_replace('.', '\\', $fileName);
                    }
                }
            }
        }

        return array_merge($classes, array_keys($this->_classCache));
    }

    public function getElement($className)
    {
        if (null === $this->_classCache) {
            $this->initialize();
        }

        if (!isset($this->_classCache[$className])) {
            $this->_classCache[$className] = parent::getElement($className);
        }

        return $this->_classCache[$className];
    }

    protected function initialize()
    {
        $this->_classCache = array();
        if (null !== $this->_globalBasename) {
            foreach ($this->_paths as $path) {
                if (file_exists($file = $path.'/'.$this->_globalBasename.$this->_fileExtension)) {
                    $this->_classCache = array_merge($this->_classCache, $this->_loadMappingFile($file));
                }
            }
        }
    }

    protected function _findMappingFile($className)
    {
        $defaultFileName = str_replace('\\', '.', $className).$this->_fileExtension;
        foreach ($this->_paths as $path) {
            if (!isset($this->_prefixes[$path])) {
                if (file_exists($path.DIRECTORY_SEPARATOR.$defaultFileName)) {
                    return $path.DIRECTORY_SEPARATOR.$defaultFileName;
                }

                continue;
            }

            $prefix = $this->_prefixes[$path];

            if (0 !== strpos($className, $prefix.'\\')) {
                continue;
            }

            $filename = $path.'/'.strtr(substr($className, strlen($prefix)+1), '\\', '.').$this->_fileExtension;
            if (file_exists($filename)) {
                return $filename;
            }

            throw MappingException::mappingFileNotFound($className, $filename);
        }

        throw MappingException::mappingFileNotFound($className, substr($className, strrpos($className, '\\') + 1).$this->_fileExtension);
    }
}
