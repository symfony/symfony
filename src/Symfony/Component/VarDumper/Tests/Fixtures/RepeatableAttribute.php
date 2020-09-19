<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Fixtures;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS_CONST | Attribute::TARGET_PROPERTY)]
final class RepeatableAttribute
{
    private string $string;

    public function __construct(string $string = 'default')
    {
        $this->string = $string;
    }

    public function getString(): string
    {
        return $this->string;
    }
}
