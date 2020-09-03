<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Types;

use Symfony\Component\Uid\Uuid;

final class UuidBinaryType extends AbstractBinaryUidType
{
    public function getName(): string
    {
        return 'uuid_binary';
    }

    protected function getUidClass(): string
    {
        return Uuid::class;
    }
}
