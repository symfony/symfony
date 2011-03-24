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

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Type;
use Symfony\Component\Form\Type\FormTypeInterface;

class ArrayTypeLoader implements TypeLoaderInterface
{
    /**
     * @var array
     */
    private $types;

    public function __construct(array $types)
    {
        foreach ($types as $type) {
            $this->types[$type->getName()] = $type;
        }
    }

    public function getType($name)
    {
        return $this->types[$name];
    }

    public function hasType($name)
    {
        return isset($this->types[$name]);
    }
}