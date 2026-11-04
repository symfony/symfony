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
 *
 * @extends NonPublicAccessorEnvelope<T>
 */
class InheritedNonPublicAccessorEnvelope extends NonPublicAccessorEnvelope
{
    public mixed $protectedRead;
    public mixed $privateRead;
    public mixed $protectedWrite;
    public mixed $privateWrite;
    public mixed $localRead;
    public mixed $localWrite;

    /** @return T */
    protected function getLocalRead(): mixed
    {
        return null;
    }

    /** @param T $value */
    private function setLocalWrite(mixed $value): void
    {
    }
}
