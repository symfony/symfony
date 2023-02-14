<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;

final class TelegramOptionsTest extends TestCase
{
    /**
     * @dataProvider validCacheTimeDataProvider
     */
    public function testAnswerCallbackQueryWithCacheTime(int $cacheTime)
    {
        $options = new TelegramOptions();

        $returnedOptions = $options->answerCallbackQuery('123', true, $cacheTime);

        $this->assertSame($options, $returnedOptions);
        $this->assertEquals(
            [
                'callback_query_id' => '123',
                'show_alert' => true,
                'cache_time' => $cacheTime,
            ],
            $options->toArray(),
        );
    }

    public static function validCacheTimeDataProvider(): iterable
    {
        yield 'cache time equals 1' => [1];
        yield 'cache time equals 2' => [2];
        yield 'cache time equals 10' => [10];
    }

    /**
     * @dataProvider invalidCacheTimeDataProvider
     */
    public function testAnswerCallbackQuery(int $cacheTime)
    {
        $options = new TelegramOptions();

        $returnedOptions = $options->answerCallbackQuery('123', true, $cacheTime);

        $this->assertSame($options, $returnedOptions);
        $this->assertEquals(
            [
                'callback_query_id' => '123',
                'show_alert' => true,
            ],
            $options->toArray(),
        );
    }

    public static function invalidCacheTimeDataProvider(): iterable
    {
        yield 'cache time equals 0' => [0];
        yield 'cache time equals -1' => [-1];
        yield 'cache time equals -10' => [-10];
    }
}
