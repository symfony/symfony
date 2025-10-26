<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DepthProxy;

use Symfony\Component\VarExporter\LazyObjectInterface;

class MockLazyProxy implements LazyObjectInterface
{
    private bool $initialized = false;

    public function __construct(
        private string $exceptionMessage,
    ) {
    }

    public function initializeLazyObject(): object
    {
        $this->initialized = true;
        throw new \RuntimeException($this->exceptionMessage);
    }

    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        return $this->initialized;
    }

    public function resetLazyObject(): bool
    {
        return false;
    }
}
