<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type\Loader;

class TypeLoaderChain implements TypeLoaderInterface
{
    private $loaders = array();

    public function addLoader(TypeLoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    public function getType($name)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->hasType($name)) {
                return $loader->getType($name);
            }
        }

        // TODO exception
    }

    public function hasType($name)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->hasType($name)) {
                return true;
            }
        }

        return false;
    }
}