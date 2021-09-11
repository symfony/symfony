<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Translatable;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translatable\DateTimeTranslatable;
use Symfony\Contracts\Translation\TranslatorInterface;

class DateTimeTranslatableTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }
    }

    /**
     * @dataProvider getValues()
     */
    public function testFormat(string $expected, DateTimeTranslatable $parameter, string $locale)
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $this->assertSame($expected, $parameter->trans($translator, $locale));
    }

    public function getValues(): iterable
    {
        $dateTime = new \DateTime('2021-01-01 23:55:00', new \DateTimeZone('UTC'));

        $parameterDateTime = new DateTimeTranslatable($dateTime);
        yield 'DateTime in French' => ['01/01/2021 23:55', $parameterDateTime, 'fr_FR'];
        yield 'DateTime in GB English' => ['01/01/2021, 23:55', $parameterDateTime, 'en_GB'];
        yield 'DateTime in US English' => ['1/1/21, 11:55 PM', $parameterDateTime, 'en_US'];

        $dateTimeParis = new \DateTime('2021-01-01 23:55:00', new \DateTimeZone('UTC'));
        $dateTimeParis->setTimezone(new \DateTimeZone('Europe/Paris'));

        $parameterDateTimeParis = new DateTimeTranslatable($dateTimeParis);
        yield 'DateTime in Paris in French' => ['02/01/2021 00:55', $parameterDateTimeParis, 'fr_FR'];
        yield 'DateTime in Paris in GB English' => ['02/01/2021, 00:55', $parameterDateTimeParis, 'en_GB'];
        yield 'DateTime in Paris in US English' => ['1/2/21, 12:55 AM', $parameterDateTimeParis, 'en_US'];

        $parameterDateParis = DateTimeTranslatable::date($dateTimeParis);
        yield 'Date in Paris in French' => ['02/01/2021', $parameterDateParis, 'fr_FR'];
        yield 'Date in Paris in GB English' => ['02/01/2021', $parameterDateParis, 'en_GB'];
        yield 'Date in Paris in US English' => ['1/2/21', $parameterDateParis, 'en_US'];

        $parameterTimeParis = DateTimeTranslatable::time($dateTimeParis);
        yield 'Time in Paris in French' => ['00:55', $parameterTimeParis, 'fr_FR'];
        yield 'Time in Paris in GB English' => ['00:55', $parameterTimeParis, 'en_GB'];
        yield 'Time in Paris in US English' => ['12:55 AM', $parameterTimeParis, 'en_US'];
    }
}
