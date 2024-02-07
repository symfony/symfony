<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Emoji\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Emoji\Emojis;

class EmojisTest extends TestCase
{
    public function testExists()
    {
        $this->assertTrue(Emojis::exists('🃏'));
        $this->assertTrue(Emojis::exists('🦇'));

        $this->assertFalse(Emojis::exists('Baker'));
        $this->assertFalse(Emojis::exists('Jokman'));
    }

    public function testGetEmojis()
    {
        $this->assertContains('🍕', Emojis::getEmojis());
        $this->assertContains('🍔', Emojis::getEmojis());
        $this->assertContains('🍟', Emojis::getEmojis());

        $this->assertContains('🍝', Emojis::getEmojis());
        $this->assertContains('🍣', Emojis::getEmojis());
        $this->assertContains('🍤', Emojis::getEmojis());

        $this->assertNotContains('€', Emojis::getEmojis());
        $this->assertNotContains('Dollar', Emojis::getEmojis());
        $this->assertNotContains('à', Emojis::getEmojis());
    }
}
