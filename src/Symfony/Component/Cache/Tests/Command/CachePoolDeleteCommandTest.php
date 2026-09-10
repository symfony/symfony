<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Command\CachePoolDeleteCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

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

        $tester = $this->getCommandTester($cachePool);
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

        $tester = $this->getCommandTester($cachePool);
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

        $tester = $this->getCommandTester($cachePool);
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application();
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

    private function getCommandTester(CacheItemPoolInterface $cachePool): CommandTester
    {
        $application = new Application();
        $application->addCommand(new CachePoolDeleteCommand(new Psr6CacheClearer(['foo' => $cachePool])));

        return new CommandTester($application->find('cache:pool:delete'));
    }
}
