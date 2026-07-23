<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\JsonPath;

use Symfony\Component\JsonPath\Attribute\AsJsonPathFunction;

#[AsJsonPathFunction('upper')]
final class UppercaseFunction
{
    public function __invoke(mixed $value): ?string
    {
        return \is_string($value) ? strtoupper($value) : null;
    }
}
