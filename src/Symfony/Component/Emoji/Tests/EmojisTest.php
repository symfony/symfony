<?php

namespace Symfony\Component\Emoji\Tests;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
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
