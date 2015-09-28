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

use Symfony\Component\Translation\Formatter\LegacyIntlMessageFormatter;

class LegacyIntlMessageFormatterTest extends IntlMessageFormatterTest
{
    /**
     * @dataProvider legacyMessages
     */
    public function testFormatLegacyMessages($expected, $message, $arguments)
    {
        $formatter = $this->getMessageFormatter();

        $this->assertEquals($expected, $formatter->format('en', $message, $arguments));
    }

    public function legacyMessages()
    {
        return array(
            array(
                'There is one apple',
                'There is one apple',
                array(),
            ),
            array(
                'There are 5 apples',
                'There are %count% apples',
                array('%count%' => 5),
            ),
            array(
                'There are 5 apples',
                'There are {{count}} apples',
                array('{{count}}' => 5),
            ),
        );
    }

    protected function getMessageFormatter()
    {
        return new LegacyIntlMessageFormatter();
    }
}
