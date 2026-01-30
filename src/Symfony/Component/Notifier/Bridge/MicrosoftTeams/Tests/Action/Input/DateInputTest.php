<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Action\Input;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\DateInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Test\Action\Input\AbstractInputTestCase;

final class DateInputTest extends AbstractInputTestCase
{
    public function createInput(): DateInput
    {
        return new DateInput();
    }

    public function testIncludeTimeWithTrue(): void
    {
        $input = $this->createInput()
            ->includeTime(true);

        $this->assertTrue($input->toArray()['includeTime']);
    }

    public function testIncludeTimeWithFalse(): void
    {
        $input = $this->createInput()
            ->includeTime(false);

        $this->assertFalse($input->toArray()['includeTime']);
    }

    public function testToArray(): void
    {
        $this->assertSame(
            [
                '@type' => 'DateInput',
            ],
            $this->createInput()->toArray()
        );
    }
}
