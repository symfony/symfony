<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extension\EscaperExtension;

class WebProfilerExtensionTest extends TestCase
{
    /**
     * @dataProvider provideMessages
     */
    public function testDumpHeaderIsDisplayed(string $message, array $context, bool $dump1HasHeader, bool $dump2HasHeader)
    {
        class_exists(CoreExtension::class); // Load twig_convert_encoding()
        class_exists(EscaperExtension::class); // Load twig_escape_filter()

        $twigEnvironment = $this->mockTwigEnvironment();
        $varCloner = new VarCloner();

        $webProfilerExtension = new WebProfilerExtension();

        $needle = 'window.Sfdump';

        $dump1 = $webProfilerExtension->dumpLog($twigEnvironment, $message, $varCloner->cloneVar($context));
        self::assertSame($dump1HasHeader, str_contains($dump1, $needle));

        $dump2 = $webProfilerExtension->dumpData($twigEnvironment, $varCloner->cloneVar([]));
        self::assertSame($dump2HasHeader, str_contains($dump2, $needle));
    }

    public static function provideMessages(): iterable
    {
        yield ['Some message', ['foo' => 'foo', 'bar' => 'bar'], false, true];
        yield ['Some message {@see some text}', ['foo' => 'foo', 'bar' => 'bar'], false, true];
        yield ['Some message {foo}', ['foo' => 'foo', 'bar' => 'bar'], true, false];
        yield ['Some message {foo}', ['bar' => 'bar'], false, true];
    }

    private function mockTwigEnvironment()
    {
        $twigEnvironment = $this->createMock(Environment::class);

        $twigEnvironment->expects($this->any())->method('getCharset')->willReturn('UTF-8');

        return $twigEnvironment;
    }
}
