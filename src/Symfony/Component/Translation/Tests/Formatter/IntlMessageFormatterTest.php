<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Formatter;

use Symfony\Component\Translation\Formatter\IntlMessageFormatter;

class IntlMessageFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped(
              'The Intl extension is not available.'
            );
        }
    }

    /**
     * @dataProvider provideDataForFormat
     */
    public function testFormat($expected, $message, $number, $arguments)
    {
        $formatter = new IntlMessageFormatter();

        $this->assertEquals($expected, trim($formatter->format('en', $message, $number, $arguments)));
    }

    public function provideDataForFormat()
    {
        $intlSampleChooseMessage = trim('
        {gender_of_host, select, 
            female {
                {num_guests, plural, offset:1 
                    =0 {{host} does not give a party.}
                    =1 {{host} invites {guest} to her party.}
                    =2 {{host} invites {guest} and one other person to her party.}
                    other {{host} invites {guest} and # other people to her party.}}}
            male {
                {num_guests, plural, offset:1 
                    =0 {{host} does not give a party.}
                    =1 {{host} invites {guest} to his party.}
                    =2 {{host} invites {guest} and one other person to his party.}
                    other {{host} invites {guest} and # other people to his party.}}}
            other {
                {num_guests, plural, offset:1 
                    =0 {{host} does not give a party.}
                    =1 {{host} invites {guest} to their party.}
                    =2 {{host} invites {guest} and one other person to their party.}
                    other {{host} invites {guest} and # other people to their party.}
                }
            }
        }
        ');

        return array(
            array(
                'There is one apple',
                'There is one apple',
                null,
                array(),
            ),
            array(
                '4,560 monkeys on 123 trees make 37.073 monkeys per tree',
                '{0,number,integer} monkeys on {1,number,integer} trees make {2,number} monkeys per tree',
                null,
                array(4560, 123, 4560/123),
            ),
            array(
                'Fabien invites Guilherme and 9 other people to his party.',
                $intlSampleChooseMessage,
                10,
                array(
                    'gender_of_host' => 'male',
                    'num_guests'     => 10,
                    'host'           => 'Fabien',
                    'guest'          => 'Guilherme',
                ),
            ),
        );
    }
}