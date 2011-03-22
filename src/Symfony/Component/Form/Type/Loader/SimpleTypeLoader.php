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

use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\Form\Exception\TypeLoaderException;

class SimpleTypeLoader implements TypeLoaderInterface
{
    private $types = array();

    public function getType($name)
    {
        if (!isset($this->types[$name])) {
            if (!class_exists($name)) {
                throw new TypeLoaderException(sprintf('The type class "%s" does not exist', $name));
            }

            $type = new $name();

            if (!$type instanceof FormTypeInterface) {
                throw new TypeLoaderException(sprintf('The type class "%s" must implement "Symfony\Component\Form\Type\FormTypeInterface"', $name));
            }

            $this->types[$name] = $type;
        }

        return $this->types[$name];
    }

    public function hasType($name)
    {
        return class_exists($name);
    }
}
