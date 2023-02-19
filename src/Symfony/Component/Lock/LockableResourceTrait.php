<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

use Symfony\Component\Uid\Uuid;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
trait LockableResourceTrait
{
    private ?Uuid $resourceIdentifier = null;

    public function getResourceIdentifier(): string
    {
        return $this->resourceIdentifier ??= Uuid::v7();
    }
}
