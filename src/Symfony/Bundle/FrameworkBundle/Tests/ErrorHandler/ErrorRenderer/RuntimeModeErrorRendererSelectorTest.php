<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\ErrorHandler\ErrorRenderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\ErrorHandler\ErrorRenderer\RuntimeModeErrorRendererSelector;
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

class RuntimeModeErrorRendererSelectorTest extends TestCase
{
    #[DataProvider('provideSelect')]
    public function testSelect(bool $isWebMode, bool $debug, string $expectedClass)
    {
        $renderer = RuntimeModeErrorRendererSelector::select(
            $isWebMode,
            static fn () => new HtmlErrorRenderer($debug),
            static fn () => new CliErrorRenderer(),
            $debug,
        );

        $this->assertInstanceOf($expectedClass, $renderer);
    }

    public static function provideSelect(): iterable
    {
        yield 'web mode with debug' => [true, true, HtmlErrorRenderer::class];
        yield 'web mode without debug' => [true, false, HtmlErrorRenderer::class];
        yield 'cli mode with debug' => [false, true, CliErrorRenderer::class];
        yield 'cli mode without debug' => [false, false, HtmlErrorRenderer::class];
    }
}
