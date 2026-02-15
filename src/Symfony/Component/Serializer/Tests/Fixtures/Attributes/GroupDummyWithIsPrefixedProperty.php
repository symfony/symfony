<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Attribute\Groups;

class GroupDummyWithIsPrefixedProperty
{
    private bool $isSomething = false;

    #[Groups(['test'])]
    public function isSomething(): bool
    {
        return $this->isSomething;
    }

    public function setIsSomething(bool $isSomething): void
    {
        $this->isSomething = $isSomething;
    }
}
