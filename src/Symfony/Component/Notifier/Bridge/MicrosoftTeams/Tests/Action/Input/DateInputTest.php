<?php

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Action\Input;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\AbstractInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\DateInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Test\Action\Input\AbstractInputTestCase;

final class DateInputTest extends AbstractInputTestCase
{
    /**
     * @return DateInput
     */
    public function createInput(): AbstractInput
    {
        return new DateInput();
    }

    public function testIncludeTimeWithTrue()
    {
        $input = $this->createInput()
            ->includeTime(true);

        $this->assertTrue($input->toArray()['includeTime']);
    }

    public function testIncludeTimeWithFalse()
    {
        $input = $this->createInput()
            ->includeTime(false);

        $this->assertFalse($input->toArray()['includeTime']);
    }

    public function testToArray()
    {
        $this->assertSame(
            [
                '@type' => 'DateInput',
            ],
            $this->createInput()->toArray()
        );
    }
}
