<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form;

use Symfony\Component\Form\Type\Loader\TypeLoaderInterface;
use Doctrine\ORM\EntityManager;

class DoctrineTypeLoader implements TypeLoaderInterface
{
    private $types;

    public function __construct(EntityManager $em)
    {
        $this->types['entity'] = new EntityType($em);
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



