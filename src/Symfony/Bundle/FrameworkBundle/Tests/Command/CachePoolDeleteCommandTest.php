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
use Symfony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\HttpKernel\KernelInterface;

class CachePoolDeleteCommandTest extends TestCase
{
    public function testCommandWithValidKey()
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(true);

        $tester = $this->getCommandTester($this->getKernel(), $cachePool);
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);

        $this->assertStringContainsString('[OK] Cache item "bar" was successfully deleted.', $tester->getDisplay());
    }

    public function testCommandWithInValidKey()
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(false);

        $cachePool->expects($this->never())
            ->method('deleteItem')
            ->with('bar');

        $tester = $this->getCommandTester($this->getKernel(), $cachePool);
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);

        $this->assertStringContainsString('[NOTE] Cache item "bar" does not exist in cache pool "foo".', $tester->getDisplay());
    }

    public function testCommandDeleteFailed()
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(false);

        $this->expectExceptionMessage('Cache item "bar" could not be deleted.');

        $tester = $this->getCommandTester($this->getKernel(), $cachePool);
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application($this->getKernel());
        $application->addCommand(new CachePoolDeleteCommand(new Psr6CacheClearer(['foo' => new ArrayAdapter()]), ['foo']));
        $tester = new CommandCompletionTester($application->get('cache:pool:delete'));

        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
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
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn(new Container());

        $kernel
            ->expects($this->once())
            ->method('getBundles')
            ->willReturn([]);

        return $kernel;
    }

    private function getCommandTester(KernelInterface $kernel, CacheItemPoolInterface $cachePool): CommandTester
    {
        $application = new Application($kernel);
        $application->addCommand(new CachePoolDeleteCommand(new Psr6CacheClearer(['foo' => $cachePool])));

        return new CommandTester($application->find('cache:pool:delete'));
    }
}
