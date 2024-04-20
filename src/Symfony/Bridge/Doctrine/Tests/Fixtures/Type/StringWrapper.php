<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures\Type;

class StringWrapper
{
    public function __construct(
        private readonly ?string $string = null
    ) {
    }

    public function getString(): string
    {
        return $this->string;
    }
}
