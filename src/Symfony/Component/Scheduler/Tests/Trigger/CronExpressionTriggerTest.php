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
use Random\Randomizer;
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
        if (class_exists(Randomizer::class)) {
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

        return [
            ['# * * * *', '36 * * * *'],
            ['# # * * *', '36 0 * * *'],
            ['# # # * *', '36 0 14 * *'],
            ['# # # # *', '36 0 14 3 *'],
            ['# # # # #', '36 0 14 3 5'],
            ['# # 1,15 1-11 *', '36 0 1,15 1-11 *'],
            ['# # 1,15 * *', '36 0 1,15 * *'],
            ['#hourly', '36 * * * *'],
            ['#daily', '36 0 * * *'],
            ['#weekly', '36 0 * * 6'],
            ['#weekly@midnight', '36 0 * * 6'],
            ['#monthly', '36 0 14 * *'],
            ['#monthly@midnight', '36 0 14 * *'],
            ['#yearly', '36 0 14 3 *'],
            ['#yearly@midnight', '36 0 14 3 *'],
            ['#annually', '36 0 14 3 *'],
            ['#annually@midnight', '36 0 14 3 *'],
            ['#midnight', '36 0 * * *'],
            ['#(1-15) * * * *', '7 * * * *'],
            ['#(1-15) * * * #(3-5)', '7 * * * 3'],
            ['#(1-15) * # * #(3-5)', '7 * 1 * 5'],
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
}
