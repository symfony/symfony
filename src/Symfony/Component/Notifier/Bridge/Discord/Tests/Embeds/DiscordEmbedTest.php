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
}
