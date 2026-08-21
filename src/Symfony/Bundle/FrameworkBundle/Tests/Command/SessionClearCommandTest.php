<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\SessionClearCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\ClearableSessionHandlerInterface;

/**
 * Mocking \SessionHandlerInterface directly is not an option: PHP 8.6 warns about
 * any class implementing it without create_sid() and validateId(), which the
 * generated doubles would not have.
 */
abstract class BareSessionHandler implements \SessionHandlerInterface
{
    abstract public function create_sid(): string;

    abstract public function validateId(string $id): bool;
}

// the interface only exists since HttpFoundation 8.2, older versions must not fatal at load time
if (interface_exists(ClearableSessionHandlerInterface::class)) {
    abstract class ClearableSessionHandler extends BareSessionHandler implements ClearableSessionHandlerInterface
    {
    }
}

class SessionClearCommandTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ClearableSessionHandlerInterface::class)) {
            $this->markTestSkipped(ClearableSessionHandlerInterface::class.' is not available.');
        }
    }

    public function testClearSucceeds()
    {
        $handler = $this->createMock(ClearableSessionHandler::class);
        $handler->expects($this->once())->method('clear');

        $tester = new CommandTester(new SessionClearCommand($handler));
        $statusCode = $tester->execute(['--force' => true]);

        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('cleared', $tester->getDisplay());
    }

    public function testUnsupportedHandler()
    {
        $handler = $this->createStub(BareSessionHandler::class);

        $tester = new CommandTester(new SessionClearCommand($handler));
        $statusCode = $tester->execute([]);

        $this->assertSame(1, $statusCode);
        $this->assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    public function testLogicExceptionIsHandled()
    {
        $handler = $this->createMock(ClearableSessionHandler::class);
        $handler->expects($this->once())
            ->method('clear')
            ->willThrowException(new \LogicException('Handler cannot clear sessions in this state.'));

        $tester = new CommandTester(new SessionClearCommand($handler));
        $statusCode = $tester->execute(['--force' => true]);

        $this->assertSame(1, $statusCode);
        $this->assertStringContainsString('Handler cannot clear sessions in this state.', $tester->getDisplay());
    }

    public function testForceSkipsConfirmation()
    {
        $handler = $this->createMock(ClearableSessionHandler::class);
        $handler->expects($this->once())->method('clear');

        $tester = new CommandTester(new SessionClearCommand($handler));
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testInteractiveConfirmationDeclinedSkipsClearing()
    {
        $handler = $this->createMock(ClearableSessionHandler::class);
        $handler->expects($this->never())->method('clear');

        $tester = new CommandTester(new SessionClearCommand($handler));
        $tester->setInputs(['no']);
        $statusCode = $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $statusCode);
    }
}
