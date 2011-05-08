<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Mapping\Driver;

use Doctrine\ORM\Mapping\Driver\YamlDriver as BaseYamlDriver;

/**
 * YamlDriver that additionnaly looks for mapping information in a global file.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class YamlDriver extends BaseYamlDriver
{
    protected $_globalFile = 'mapping';
    protected $_classCache;
    protected $_fileExtension = '.orm.yml';

    public function isTransient($className)
    {
        return !in_array($className, $this->getAllClassNames());
    }

    public function getAllClassNames()
    {
        if (null === $this->_classCache) {
            $this->initialize();
        }

        return array_merge(parent::getAllClassNames(), array_keys($this->_classCache));
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
        foreach ($this->_paths as $path) {
            if (file_exists($file = $path.'/'.$this->_globalFile.$this->_fileExtension)) {
                $this->_classCache = array_merge($this->_classCache, $this->_loadMappingFile($file));
            }
        }
    }
}
