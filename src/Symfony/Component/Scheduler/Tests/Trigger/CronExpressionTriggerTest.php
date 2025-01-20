<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;

class CronExpressionTriggerTest extends TestCase
{
    /**
     * @dataProvider hashedExpressionProvider
     */
    public function testHashedExpressionParsing(string $input, string $expected)
    {
        $triggerA = CronExpressionTrigger::fromSpec($input, 'my task');
        $triggerB = CronExpressionTrigger::fromSpec($input, 'my task');
        $triggerC = CronExpressionTrigger::fromSpec($input, 'another task');

        $this->assertSame($expected, (string) $triggerA);
        $this->assertSame((string) $triggerB, (string) $triggerA);
        $this->assertNotSame((string) $triggerC, (string) $triggerA);
    }

    public static function hashedExpressionProvider(): array
    {
        return [
            ['# * * * *', '30 * * * *'],
            ['# # * * *', '30 0 * * *'],
            ['# # # * *', '30 0 25 * *'],
            ['# # # # *', '30 0 25 10 *'],
            ['# # # # #', '30 0 25 10 5'],
            ['# # 1,15 1-11 *', '30 0 1,15 1-11 *'],
            ['# # 1,15 * *', '30 0 1,15 * *'],
            ['#hourly', '30 * * * *'],
            ['#daily', '30 0 * * *'],
            ['#weekly', '30 0 * * 3'],
            ['#weekly@midnight', '30 0 * * 3'],
            ['#monthly', '30 0 25 * *'],
            ['#monthly@midnight', '30 0 25 * *'],
            ['#yearly', '30 0 25 10 *'],
            ['#yearly@midnight', '30 0 25 10 *'],
            ['#annually', '30 0 25 10 *'],
            ['#annually@midnight', '30 0 25 10 *'],
            ['#midnight', '30 0 * * *'],
            ['#(1-15) * * * *', '1 * * * *'],
            ['#(1-15) * * * #(3-5)', '1 * * * 3'],
            ['#(1-15) * # * #(3-5)', '1 * 17 * 5'],
        ];
    }

    public function testHashFieldsAreRandomizedIndependently()
    {
        $parts = explode(' ', (string) CronExpressionTrigger::fromSpec('#(1-6) #(1-6) #(1-6) #(1-6) #(1-6)', 'some context'));

        $this->assertNotCount(1, array_unique($parts));
    }

    public function testFromHashWithStandardExpression()
    {
        $this->assertSame('56 20 1 9 0', (string) CronExpressionTrigger::fromSpec('56 20 1 9 0', 'some context'));
        $this->assertSame('0 0 * * *', (string) CronExpressionTrigger::fromSpec('@daily'));
    }

    public function testDefaultTimezone()
    {
        $now = new \DateTimeImmutable('Europe/Paris');
        $trigger = CronExpressionTrigger::fromSpec('0 12 * * *');
        $this->assertSame('Europe/Paris', $trigger->getNextRunDate($now)->getTimezone()->getName());
    }

    public function testTimezoneIsUsed()
    {
        $now = new \DateTimeImmutable('Europe/Paris');
        $timezone = new \DateTimeZone('UTC');
        $trigger = CronExpressionTrigger::fromSpec('0 12 * * *', null, $timezone);
        $this->assertSame('UTC', $trigger->getNextRunDate($now)->getTimezone()->getName());
    }
}
