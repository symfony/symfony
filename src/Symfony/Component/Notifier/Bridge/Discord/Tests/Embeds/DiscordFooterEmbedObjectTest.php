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
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFooterEmbedObject;
use Symfony\Component\Notifier\Exception\LengthException;

final class DiscordFooterEmbedObjectTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $author = (new DiscordFooterEmbedObject())
            ->text('foo')
            ->iconUrl('http://icon-ur.l')
            ->proxyIconUrl('http://proxy-icon-ur.l');

        $this->assertSame([
            'text' => 'foo',
            'icon_url' => 'http://icon-ur.l',
            'proxy_icon_url' => 'http://proxy-icon-ur.l',
        ], $author->toArray());
    }

    public function testThrowsWhenTextExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the text is 2048 characters.');

        (new DiscordFooterEmbedObject())->text(str_repeat('h', 2049));
    }
}
