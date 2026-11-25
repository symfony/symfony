<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\EnvironmentValues;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

final class EnvironmentValuesTest extends TestCase
{
    public function testDeviceLookupAndFallbackDoNotAssumeTerminalMetrics()
    {
        $values = ['safe-area-inset-top' => '12.5px', 'viewport-segment-width 0 0' => '320.5px'];
        self::assertSame('calc(12.5px + 2px) 320.5px "env(missing)" red, blue', EnvironmentValues::substitute('calc(env(safe-area-inset-top) + 2px) env(viewport-segment-width 0 0) "env(missing)" env(missing, red, blue)', static fn (string $name): ?string => $values[$name] ?? null));
    }

    public function testUnknownVariableWithoutFallbackIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        EnvironmentValues::substitute('env(missing)', static fn (): ?string => null);
    }
}
