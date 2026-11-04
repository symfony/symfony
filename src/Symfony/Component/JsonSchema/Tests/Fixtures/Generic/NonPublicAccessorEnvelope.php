<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures\Generic;

/**
 * @template T
 */
class NonPublicAccessorEnvelope
{
    /** @return T */
    protected function getProtectedRead(): mixed
    {
        return null;
    }

    /** @return T */
    private function getPrivateRead(): mixed
    {
        return null;
    }

    /** @param T $value */
    protected function setProtectedWrite(mixed $value): void
    {
    }

    /** @param T $value */
    private function setPrivateWrite(mixed $value): void
    {
    }
}
