<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\BorderPattern;

class BorderPatternTest extends TestCase
{
    #[DataProvider('allPatternsProvider')]
    public function testFromNameReturnsExpectedCharsAndIsNone(string $name, ?string $method, bool $isNone)
    {
        $fromName = BorderPattern::fromName($name);

        if (null !== $method) {
            $fromMethod = BorderPattern::$method();
            $this->assertSame($fromMethod->getChars(), $fromName->getChars());
            $this->assertSame($fromMethod->getStrategies(), $fromName->getStrategies());
        }

        $this->assertSame($isNone, $fromName->isNone());
    }

    /**
     * @return iterable<string, array{string, ?string, bool}>
     */
    public static function allPatternsProvider(): iterable
    {
        yield 'none' => [BorderPattern::NONE, null, true];
        yield 'normal' => [BorderPattern::NORMAL, 'normal', false];
        yield 'rounded' => [BorderPattern::ROUNDED, 'rounded', false];
        yield 'double' => [BorderPattern::DOUBLE, 'double', false];
        yield 'tall' => [BorderPattern::TALL, 'tall', false];
        yield 'wide' => [BorderPattern::WIDE, 'wide', false];
        yield 'tall-medium' => [BorderPattern::TALL_MEDIUM, 'tallMedium', false];
        yield 'wide-medium' => [BorderPattern::WIDE_MEDIUM, 'wideMedium', false];
        yield 'tall-large' => [BorderPattern::TALL_LARGE, 'tallLarge', false];
        yield 'wide-large' => [BorderPattern::WIDE_LARGE, 'wideLarge', false];
    }

    public function testFromNameThrowsOnUnknown()
    {
        $this->expectException(InvalidArgumentException::class);
        BorderPattern::fromName('unknown');
    }
}
