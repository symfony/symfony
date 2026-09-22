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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class WebProfilerExtensionTest extends TestCase
{
    #[DataProvider('provideMessages')]
    public function testDumpHeaderIsDisplayed(string $message, array $context, bool $dump1HasHeader, bool $dump2HasHeader)
    {
        $twigEnvironment = new Environment(new ArrayLoader());
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

    #[DataProvider('provideBytes')]
    public function testFormatBytes(int|float $bytes, string $expected)
    {
        self::assertSame($expected, (new WebProfilerExtension())->formatBytes($bytes));
    }

    public static function provideBytes(): iterable
    {
        yield [0, '0 bytes'];
        yield [999, '999 bytes'];
        yield [1000, '1.00 kB'];
        yield [1536, '1.54 kB'];
        yield [999999, '1,000.00 kB'];
        yield [1000 ** 2, '1.00 MB'];
        yield [1.5 * 1000 ** 3, '1.50 GB'];
        yield [2.5 * 1000 ** 4, '2.50 TB'];
        yield [1000 ** 5, '1.00 PB'];
        yield [1000 ** 6, '1,000.00 PB'];
    }
}
