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

use Symfony\Component\Messenger\Command\ShowMessagesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * @author Payene Denis Kombate <denis.kombate@gmail.com>
 */
class ShowMessagesCommandTest extends TestCase
{
    
    public function testExecuteDisplaysMessagesSuccessfully(): void
    {
        // 1. On crée un faux message PHP
        $mockMessage = new \stdClass();
        $envelope = new Envelope($mockMessage, [new TransportMessageIdStamp('123')]);

        // 2. On simule (mock) le Receiver qui implémente ListableReceiverInterface
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())
            ->method('all')
            ->with(50)
            ->willReturn([$envelope]);

        // 3. On simule le ServiceLocator qui contient notre faux transport "async"
        $transportLocator = $this->createMock(ServiceLocator::class);
        $transportLocator->expects($this->once())
            ->method('has')
            ->with('async')
            ->willReturn(true);
            
        $transportLocator->expects($this->once())
            ->method('get')
            ->with('async')
            ->willReturn($receiver);

        // 4. On instancie la commande et on fusionne la définition pour valider les options graphiques
        $command = new ShowMessagesCommand($transportLocator);
        $command->mergeApplicationDefinition(); 
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--transport' => 'async',
        ]);

        // 5. Assertions : On vérifie que la commande s'est bien exécutée et affiche l'ID
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('123', $output);
        $this->assertStringContainsString('stdClass', $output);
    }

    public function testExecuteOutputsTableOfMessages(): void
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        
        // On simule une enveloppe contenant un message générique
        $envelope = new Envelope(new \stdClass());
        $receiver->method('all')->with(50)->willReturn([$envelope]);

        $locator = $this->createMock(ServiceLocator::class);
        $locator->method('has')->with('async')->willReturn(true);
        $locator->method('get')->with('async')->willReturn($receiver);

        // CORRECTION : On passe uniquement le $locator (1 seul argument attendu par le constructeur)
        $command = new ShowMessagesCommand($locator);
        $command->mergeApplicationDefinition(); 
        $tester = new CommandTester($command);

        $tester->execute(['--transport' => 'async']);

        $this->assertSame(0, $tester->getStatusCode());
        
        // On vérifie les entêtes du tableau SymfonyStyle dans la sortie console
        $display = $tester->getDisplay();
        $this->assertStringContainsString('ID', $display);
        $this->assertStringContainsString('Message Class', $display);
        $this->assertStringContainsString('stdClass', $display);
    }
}
