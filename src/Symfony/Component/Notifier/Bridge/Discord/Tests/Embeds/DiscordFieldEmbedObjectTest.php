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
}
