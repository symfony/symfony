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

        self::assertTrue($input->toArray()['includeTime']);
    }

    public function testIncludeTimeWithFalse()
    {
        $input = $this->createInput()
            ->includeTime(false);

        self::assertFalse($input->toArray()['includeTime']);
    }

    public function testToArray()
    {
        self::assertSame([
            '@type' => 'DateInput',
        ], $this->createInput()->toArray());
    }
}
