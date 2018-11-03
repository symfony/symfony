<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;

class ConsumeMessagesCommandTest extends TestCase
{
    public function testConfigurationWithDefaultReceiver()
    {
        $command = new ConsumeMessagesCommand($this->createMock(ServiceLocator::class), $this->createMock(ServiceLocator::class), null, array('amqp'));
        $inputArgument = $command->getDefinition()->getArgument('receiver');
        $this->assertFalse($inputArgument->isRequired());
        $this->assertSame('amqp', $inputArgument->getDefault());
    }

    public function testConfigurationWithoutDefaultReceiver()
    {
        $command = new ConsumeMessagesCommand($this->createMock(ServiceLocator::class), $this->createMock(ServiceLocator::class), null, array('amqp', 'dummy'));
        $inputArgument = $command->getDefinition()->getArgument('receiver');
        $this->assertTrue($inputArgument->isRequired());
        $this->assertNull($inputArgument->getDefault());
    }
}
