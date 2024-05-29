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
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\Exception\LengthException;

final class DiscordFieldEmbedObjectTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $field = (new DiscordFieldEmbedObject())
            ->name('foo')
            ->value('bar')
            ->inline(true);

        $this->assertSame([
            'name' => 'foo',
            'value' => 'bar',
            'inline' => true,
        ], $field->toArray());
    }

    public function testThrowsWhenNameExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the name is 256 characters.');

        (new DiscordFieldEmbedObject())->name(str_repeat('h', 257));
    }

    public function testThrowsWhenValueExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the value is 1024 characters.');

        (new DiscordFieldEmbedObject())->value(str_repeat('h', 1025));
    }
}
