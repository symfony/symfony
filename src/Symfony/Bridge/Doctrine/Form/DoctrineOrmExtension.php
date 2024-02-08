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

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\FormTypeGuesserInterface;

class DoctrineOrmExtension extends AbstractExtension
{
    protected ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    protected function loadTypes(): array
    {
        return [
            new EntityType($this->registry),
        ];
    }

    protected function loadTypeGuesser(): ?FormTypeGuesserInterface
    {
        return new DoctrineOrmTypeGuesser($this->registry);
    }
}
