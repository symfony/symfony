<?php

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Action;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\InvokeAddInCommandAction;

final class InvokeAddInCommandActionTest extends TestCase
{
    public function testName()
    {
        $action = (new InvokeAddInCommandAction())
            ->name($value = 'My name');

        $this->assertSame($value, $action->toArray()['name']);
    }

    public function testAddInId()
    {
        $action = (new InvokeAddInCommandAction())
            ->addInId($value = '1234');

        $this->assertSame($value, $action->toArray()['addInId']);
    }

    public function testDesktopCommandId()
    {
        $action = (new InvokeAddInCommandAction())
            ->desktopCommandId($value = '324');

        $this->assertSame($value, $action->toArray()['desktopCommandId']);
    }

    public function testInitializationContext()
    {
        $value = [
            'foo' => 'bar',
        ];

        $action = (new InvokeAddInCommandAction())
            ->initializationContext($value);

        $this->assertSame($value, $action->toArray()['initializationContext']);
    }

    public function testToArray()
    {
        $this->assertSame(
            [
                '@type' => 'InvokeAddInCommand',
            ],
            (new InvokeAddInCommandAction())->toArray()
        );
    }
}
