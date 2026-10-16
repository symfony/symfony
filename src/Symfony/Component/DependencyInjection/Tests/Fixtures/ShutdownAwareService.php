<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\ShutdownInterface;

class ShutdownAwareService implements ShutdownInterface
{
    public array $calls = [];

    public function reset(bool $shutdown = false): void
    {
        $this->calls[] = $shutdown;
    }

    public function clear(bool $shutdown = false): void
    {
        $this->calls[] = $shutdown;
    }
}
