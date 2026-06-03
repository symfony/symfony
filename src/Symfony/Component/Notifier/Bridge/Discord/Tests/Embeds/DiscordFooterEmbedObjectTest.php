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
}
