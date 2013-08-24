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

/**
 * @since v2.0.0
 */
class DoctrineOrmExtension extends AbstractExtension
{
    protected $registry;

    /**
     * @since v2.1.0
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @since v2.0.0
     */
    protected function loadTypes()
    {
        return array(
            new Type\EntityType($this->registry, PropertyAccess::getPropertyAccessor()),
        );
    }

    /**
     * @since v2.0.0
     */
    protected function loadTypeGuesser()
    {
        return new DoctrineOrmTypeGuesser($this->registry);
    }
}
