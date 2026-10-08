<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Scopes EntityExistsFieldsEntity, as a multi-tenant filter would.
 */
class EntityExistsFieldsFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (EntityExistsFieldsEntity::class !== $targetEntity->getName()) {
            return '';
        }

        return $targetTableAlias.'.id <= '.$this->getParameter('maxId');
    }
}
