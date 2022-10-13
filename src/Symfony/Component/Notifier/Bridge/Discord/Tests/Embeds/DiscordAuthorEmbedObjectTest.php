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
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordAuthorEmbedObject;
use Symfony\Component\Notifier\Exception\LengthException;

final class DiscordAuthorEmbedObjectTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $author = (new DiscordAuthorEmbedObject())
            ->name('Doe')
            ->url('http://ur.l')
            ->iconUrl('http://icon-ur.l')
            ->proxyIconUrl('http://proxy-icon-ur.l');

        $this->assertSame([
            'name' => 'Doe',
            'url' => 'http://ur.l',
            'icon_url' => 'http://icon-ur.l',
            'proxy_icon_url' => 'http://proxy-icon-ur.l',
        ], $author->toArray());
    }

    public function testThrowsWhenNameExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the name is 256 characters.');

        (new DiscordAuthorEmbedObject())->name(str_repeat('h', 257));
    }
}
