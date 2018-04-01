<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Form;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symphony\Bridge\Doctrine\Form\Type\EntityType;
use Symphony\Component\Form\AbstractExtension;

class DoctrineOrmExtension extends AbstractExtension
{
    protected $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    protected function loadTypes()
    {
        return array(
            new EntityType($this->registry),
        );
    }

    protected function loadTypeGuesser()
    {
        return new DoctrineOrmTypeGuesser($this->registry);
    }
}
