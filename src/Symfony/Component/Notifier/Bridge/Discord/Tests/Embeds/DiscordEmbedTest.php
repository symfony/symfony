<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Tests\Embeds;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\Exception\LengthException;

final class DiscordEmbedTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $embed = (new DiscordEmbed())
            ->title('foo')
            ->description('bar')
            ->addField((new DiscordFieldEmbedObject())
                ->name('baz')
                ->value('qux')
            );

        $this->assertSame([
            'title' => 'foo',
            'description' => 'bar',
            'fields' => [
                [
                    'name' => 'baz',
                    'value' => 'qux',
                ],
            ],
        ], $embed->toArray());
    }

    public function testThrowsWhenTitleExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the title is 256 characters.');

        (new DiscordEmbed())->title(str_repeat('h', 257));
    }

    public function testThrowsWhenDescriptionExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the description is 4096 characters.');

        (new DiscordEmbed())->description(str_repeat('h', 4097));
    }

    public function testThrowsWhenFieldsLimitReached()
    {
        $embed = new DiscordEmbed();
        for ($i = 0; $i < 25; ++$i) {
            $embed->addField((new DiscordFieldEmbedObject())
                ->name('baz')
                ->value('qux')
            );
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Maximum number of fields should not exceed 25.');

        $embed->addField((new DiscordFieldEmbedObject())
            ->name('fail')
            ->value('fail')
        );
    }
}
