<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

class UnionProperties
{
    public int|string $identifier;
    public int|string|null $optionalIdentifier;
    public Author|SelfReferencingCategory $owner;
    public mixed $anything;
}
