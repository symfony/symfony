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
        $this->cachePool = self::createMock(CacheItemPoolInterface::class);
    }

    public function testCommandWithValidKey()
    {
        $this->cachePool->expects(self::once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $this->cachePool->expects(self::once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(true);

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);

        self::assertStringContainsString('[OK] Cache item "bar" was successfully deleted.', $tester->getDisplay());
    }

    public function testCommandWithInValidKey()
    {
        $this->cachePool->expects(self::once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(false);

        $this->cachePool->expects(self::never())
            ->method('deleteItem')
            ->with('bar');

        $tester = $this->getCommandTester($this->getKernel());
        $tester->execute(['pool' => 'foo', 'key' => 'bar']);

        self::assertStringContainsString('[NOTE] Cache item "bar" does not exist in cache pool "foo".', $tester->getDisplay());
    }

    public function testCommandDeleteFailed()
    {
        $this->cachePool->expects(self::once())
            ->method('hasItem')
            ->with('bar')
            ->willReturn(true);

        $this->cachePool->expects(self::once())
            ->method('deleteItem')
            ->with('bar')
            ->willReturn(false);

        self::expectExceptionMessage('Cache item "bar" could not be deleted.');

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

        self::assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'pool_name' => [
            ['f'],
            ['foo'],
        ];
    }

    /**
     * @return MockObject&KernelInterface
     */
    private function getKernel(): KernelInterface
    {
        $container = self::createMock(ContainerInterface::class);

        $kernel = self::createMock(KernelInterface::class);
        $kernel
            ->expects(self::any())
            ->method('getContainer')
            ->willReturn($container);

        $kernel
            ->expects(self::once())
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
