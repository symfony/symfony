<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\PropertySyntax;
use Symfony\Component\Tui\Style\MediaContext;

final class PropertySyntaxTest extends TestCase
{
    #[DataProvider('provideValues')]
    public function testMatches(string $syntax, string $value, bool $matches)
    {
        $this->assertSame($matches, PropertySyntax::matches($syntax, $value, new MediaContext()));
    }

    public static function provideValues(): iterable
    {
        yield ['*', 'anything at all', true];
        yield ['<color>', 'rebeccapurple', true];
        yield ['<color>', '10px', false];
        // Transparent, like the initial values of the registered properties of Tailwind
        yield ['<color>', '#0000', true];
        yield ['<color>', 'rgb(0 0 0 / 0)', true];
        yield ['<length>', '0', true];
        yield ['<length>', '2.5ch', true];
        yield ['<length>', '2', false];
        yield ['<length-percentage>', '50%', true];
        yield ['<integer>', '-3', true];
        yield ['<integer>', '3.5', false];
        yield ['<number>', '1e3', true];
        yield ['<angle>', '0.5turn', true];
        yield ['<time>', '150ms', true];
        yield ['auto | <length>', 'auto', true];
        yield ['auto | <length>', 'none', false];
        yield ['<length>+', '1ch 2ch', true];
        yield ['<length>+', '1ch blue', false];
        yield ['<color>#', 'red, #00ff00', true];
        yield ['<custom-ident>', 'compact', true];
        yield ['<custom-ident>', 'inherit', false];
        yield ['<length>', 'calc(1ch + 2px)', true];
    }
}
