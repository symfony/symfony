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

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\HttpKernel\KernelInterface;

class CachePoolDeleteCommandTest extends TestCase
{
    private $cachePool;

    protected function setUp(): void
    {
        $this->cachePool = $this->createMock(CacheItemPoolInterface::class);
    }

    public function testCommandWithValidKey()
    {
        $this->cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(true);

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);

        $this->assertStringContainsString('[OK] Cache item "bar" was successfully deleted.', $tester->getDisplay());
    }

    public function testCommandWithInValidKey()
    {
        $this->cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(false);

        $this->cachePool->expects($this->never())
            ->method('deleteItem')
            ->with('bar');

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);

        $this->assertStringContainsString('[NOTE] Cache item "bar" does not exist in cache pool "foo".', $tester->getDisplay());
    }

    public function testCommandDeleteFailed()
    {
        $this->cachePool->expects($this->once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(false);

        $this->expectExceptionMessage('Cache item "bar" could not be deleted.');

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application($this->getKernel());
        $application->add(new CachePoolDeleteCommand(new Psr6CacheClearer(['foo' => $this->cachePool]), ['foo']));
        $tester = new CommandCompletionTester($application->get('cache:pool:delete'));

        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions()
    {
        yield 'pool_name' => [
            ['f'],
            ['foo'],
        ];
    }

    private function getKernel(): MockObject&KernelInterface
    {
        $container = $this->createMock(ContainerInterface::class);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $kernel
            ->expects($this->once())
            ->method('getBundles')
            ->willReturn([]);

        return $kernel;
    }

    private function getCommandTester(KernelInterface $kernel): CommandTester
    {
        $application = new Application($kernel);
        $application->add(new CachePoolDeleteCommand(new Psr6CacheClearer(['foo' => $this->cachePool])));

        return new CommandTester($application->find('cache:pool:delete'));
    }
}
