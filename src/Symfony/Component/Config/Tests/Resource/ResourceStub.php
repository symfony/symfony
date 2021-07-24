<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

class ResourceStub implements SelfCheckingResourceInterface
{
    private $fresh = true;

    public function setFresh(bool $isFresh): void
    {
        $this->fresh = $isFresh;
    }

    public function __toString(): string
    {
        return 'stub';
    }

    public function isFresh(int $timestamp): bool
    {
        return $this->fresh;
    }
}
