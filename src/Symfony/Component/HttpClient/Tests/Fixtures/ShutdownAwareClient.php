<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Fixtures;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\Service\ShutdownInterface;

final class ShutdownAwareClient extends MockHttpClient implements ShutdownInterface
{
    public array $resets = [];

    public function reset(bool $shutdown = false): void
    {
        $this->resets[] = $shutdown;
        parent::reset();
    }
}
