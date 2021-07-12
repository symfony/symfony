<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Parameter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Parameter\DecimalParameter;

class DecimalParameterTest extends TestCase
{
    /**
     * @dataProvider getValues()
     */
    public function testFormat(string $expected, DecimalParameter $parameter, string $locale)
    {
        // Non-breakable spaces are added differently depending the PHP version
        $cleaned = str_replace(["\u{202f}", "\u{a0}"], ['', ''], $parameter->format($locale));
        $this->assertSame($expected, $cleaned);
    }

    public function getValues(): iterable
    {
        $parameter = new DecimalParameter(1000);

        yield 'French' => ['1000', $parameter, 'fr_FR'];
        yield 'US English' => ['1,000', $parameter, 'en_US'];

        $parameter = new DecimalParameter(1000.01);

        yield 'Float in French' => ['1000,01', $parameter, 'fr_FR'];
        yield 'Float in US English' => ['1,000.01', $parameter, 'en_US'];

        $parameter = new DecimalParameter(1, \NumberFormatter::PERCENT);

        yield 'Styled in French' => ['100%', $parameter, 'fr_FR'];
        yield 'Styled in US English' => ['100%', $parameter, 'en_US'];
    }
}
