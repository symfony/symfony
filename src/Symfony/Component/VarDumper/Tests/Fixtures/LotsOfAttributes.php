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

#[MyAttribute]
final class LotsOfAttributes
{
    #[RepeatableAttribute('one'), RepeatableAttribute('two')]
    public const SOME_CONSTANT = 'some value';

    #[MyAttribute('one', extra: 'hello')]
    private string $someProperty;

    #[MyAttribute('two')]
    public function someMethod(
        #[MyAttribute('three')] string $someParameter,
    ): void {
    }
}
