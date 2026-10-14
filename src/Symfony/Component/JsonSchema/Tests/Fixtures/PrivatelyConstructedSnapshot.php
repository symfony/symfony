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

class PrivatelyConstructedSnapshot
{
    private function __construct(
        public string $version,
    ) {
    }

    public static function capture(string $version): self
    {
        return new self($version);
    }
}
