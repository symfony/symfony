<?php

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Action\Element;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Element\Header;

final class HeaderTest extends TestCase
{
    public function testName()
    {
        $action = (new Header())
            ->name($value = 'My name');

        $this->assertSame($value, $action->toArray()['name']);
    }

    public function testValue()
    {
        $action = (new Header())
            ->value($value = 'The value...');

        $this->assertSame($value, $action->toArray()['value']);
    }
}
