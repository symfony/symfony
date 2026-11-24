<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

class ErrorRendererTest extends AbstractWebTestCase
{
    #[DataProvider('provideCliModeErrorRenderer')]
    public function testCliModeErrorRenderer(bool $debug, string $expectedClass)
    {
        $_SERVER['APP_RUNTIME_MODE'] = 'web=0';

        try {
            self::bootKernel(['test_case' => 'ErrorHandler', 'root_config' => 'config.yml', 'environment' => $debug ? 'cli_debug' : 'cli', 'debug' => $debug]);

            $this->assertInstanceOf($expectedClass, self::getContainer()->get('error_renderer.default'));
        } finally {
            unset($_SERVER['APP_RUNTIME_MODE']);
        }
    }

    public static function provideCliModeErrorRenderer(): iterable
    {
        yield 'with debug' => [true, CliErrorRenderer::class];
        yield 'without debug' => [false, HtmlErrorRenderer::class];
    }
}
