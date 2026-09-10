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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolClearCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\HttpKernel\KernelInterface;

class CachePoolClearCommandTest extends TestCase
{
    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application($this->getKernel());
        $application->addCommand(new CachePoolClearCommand(new Psr6CacheClearer(['foo' => new ArrayAdapter()]), ['foo']));
        $tester = new CommandCompletionTester($application->get('cache:pool:clear'));

        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function testTheWarningNamesThePoolThatCouldNotBeCleared()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('clear')->willReturn(false);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with('foo')->willReturn($pool);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->expects($this->once())->method('getBundles')->willReturn([]);

        $application = new Application($kernel);
        $application->addCommand(new CachePoolClearCommand(new Psr6CacheClearer()));

        $tester = new CommandTester($application->find('cache:pool:clear'));
        $tester->execute(['pools' => ['foo']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Cache pool "foo" could not be cleared.', $tester->getDisplay());
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'pool_name' => [
            ['f'],
            ['foo'],
        ];
    }

    private function getKernel(): MockObject&KernelInterface
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getContainer')
            ->willReturn(new Container());

        $kernel
            ->expects($this->once())
            ->method('getBundles')
            ->willReturn([]);

        return $kernel;
    }
}
