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
use Symfony\Component\Translation\Parameter\DateTimeParameter;

class DateTimeParameterTest extends TestCase
{
    /**
     * @dataProvider getValues()
     */
    public function testFormat(string $expected, DateTimeParameter $parameter, string $locale)
    {
        $this->assertSame($expected, $parameter->format($locale));
    }

    public function getValues(): iterable
    {
        $dateTime = new \DateTime('2021-01-01 23:55:00', new \DateTimeZone('UTC'));

        $parameterDateTime = new DateTimeParameter($dateTime);
        yield 'DateTime in French' => ['01/01/2021 23:55', $parameterDateTime, 'fr_FR'];
        yield 'DateTime in GB English' => ['01/01/2021, 23:55', $parameterDateTime, 'en_GB'];
        yield 'DateTime in US English' => ['1/1/21, 11:55 PM', $parameterDateTime, 'en_US'];

        $dateTimeParis = new \DateTime('2021-01-01 23:55:00', new \DateTimeZone('UTC'));
        $dateTimeParis->setTimezone(new \DateTimeZone('Europe/Paris'));

        $parameterDateTimeParis = new DateTimeParameter($dateTimeParis);
        yield 'DateTime in Paris in French' => ['02/01/2021 00:55', $parameterDateTimeParis, 'fr_FR'];
        yield 'DateTime in Paris in GB English' => ['02/01/2021, 00:55', $parameterDateTimeParis, 'en_GB'];
        yield 'DateTime in Paris in US English' => ['1/2/21, 12:55 AM', $parameterDateTimeParis, 'en_US'];

        $parameterDateParis = DateTimeParameter::date($dateTimeParis);
        yield 'Date in Paris in French' => ['02/01/2021', $parameterDateParis, 'fr_FR'];
        yield 'Date in Paris in GB English' => ['02/01/2021', $parameterDateParis, 'en_GB'];
        yield 'Date in Paris in US English' => ['1/2/21', $parameterDateParis, 'en_US'];

        $parameterTimeParis = DateTimeParameter::time($dateTimeParis);
        yield 'Time in Paris in French' => ['00:55', $parameterTimeParis, 'fr_FR'];
        yield 'Time in Paris in GB English' => ['00:55', $parameterTimeParis, 'en_GB'];
        yield 'Time in Paris in US English' => ['12:55 AM', $parameterTimeParis, 'en_US'];
    }
}
