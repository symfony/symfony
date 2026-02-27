<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;

final class TelegramOptionsTest extends TestCase
{
    #[DataProvider('validCacheTimeDataProvider')]
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

    #[DataProvider('invalidCacheTimeDataProvider')]
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

    public function testTelegramOptions()
    {
        $options = new TelegramOptions([
            'chat_id' => '123456',
            'parse_mode' => 'HTML',
        ]);

        $this->assertSame([
            'chat_id' => '123456',
            'parse_mode' => 'HTML',
        ], $options->toArray());

        $this->assertSame('123456', $options->getRecipientId());
    }

    public function testTelegramOptionsWithAllMethods()
    {
        $options = (new TelegramOptions())
            ->chatId('123456')
            ->parseMode(TelegramOptions::PARSE_MODE_MARKDOWN_V2)
            ->disableWebPagePreview(true)
            ->disableNotification(true)
            ->replyTo(789);

        $expected = [
            'chat_id' => '123456',
            'parse_mode' => 'MarkdownV2',
            'disable_web_page_preview' => true,
            'disable_notification' => true,
            'reply_to_message_id' => 789,
        ];

        $this->assertSame($expected, $options->toArray());
        $this->assertSame('123456', $options->getRecipientId());
    }

    public function testParseModeConstants()
    {
        $this->assertSame('HTML', TelegramOptions::PARSE_MODE_HTML);
        $this->assertSame('Markdown', TelegramOptions::PARSE_MODE_MARKDOWN);
        $this->assertSame('MarkdownV2', TelegramOptions::PARSE_MODE_MARKDOWN_V2);
    }

    public function testWithReplyMarkup()
    {
        $button1 = new InlineKeyboardButton('Button 1');
        $button1->url('https://example.com');

        $button2 = new InlineKeyboardButton('Button 2');
        $button2->callbackData('callback_2');

        $markup = new InlineKeyboardMarkup();
        $markup->inlineKeyboard([$button1, $button2]);

        $options = (new TelegramOptions())
            ->chatId('123456')
            ->replyMarkup($markup);

        $expected = [
            'chat_id' => '123456',
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Button 1', 'url' => 'https://example.com'],
                        ['text' => 'Button 2', 'callback_data' => 'callback_2'],
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $options->toArray());
    }

    public function testOptionsChaining()
    {
        $options = new TelegramOptions();

        $result = $options
            ->chatId('987654')
            ->parseMode(TelegramOptions::PARSE_MODE_HTML)
            ->disableNotification(false);

        $this->assertSame($options, $result);
        $this->assertSame([
            'chat_id' => '987654',
            'parse_mode' => 'HTML',
            'disable_notification' => false,
        ], $options->toArray());
    }

    public function testEmptyOptions()
    {
        $options = new TelegramOptions();

        $this->assertSame([], $options->toArray());
        $this->assertNull($options->getRecipientId());
    }
}
