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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Formatter\MessageFormatter;

class MessageFormatterTest extends TestCase
{
    /**
     * @dataProvider getTransMessages
     */
    public function testFormat($expected, $message, $parameters = [])
    {
        $this->assertEquals($expected, $this->getMessageFormatter()->format($message, 'en', $parameters));
    }

    public function getTransMessages()
    {
        return [
            [
                'There is one apple',
                'There is one apple',
            ],
            [
                'There are 5 apples',
                'There are %count% apples',
                ['%count%' => 5],
            ],
            [
                'There are 5 apples',
                'There are {{count}} apples',
                ['{{count}}' => 5],
            ],
        ];
    }

    private function getMessageFormatter()
    {
        return new MessageFormatter();
    }
}
