<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\PropertyAccess\PropertyAccess;

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
            new Type\EntityType($this->registry, PropertyAccess::getPropertyAccessor()),
        );
    }

    protected function loadTypeGuesser()
    {
        return new DoctrineOrmTypeGuesser($this->registry);
    }
}
