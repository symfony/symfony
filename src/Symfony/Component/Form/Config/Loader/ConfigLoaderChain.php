<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config\Loader;

class ConfigLoaderChain implements ConfigLoaderInterface
{
    private $loaders = array();

    public function addLoader(ConfigLoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    public function getConfig($identifier)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->hasConfig($identifier)) {
                return $loader->getConfig($identifier);
            }
        }

        // TODO exception
    }

    public function hasConfig($identifier)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->hasConfig($identifier)) {
                return true;
            }
        }

        return false;
    }
}