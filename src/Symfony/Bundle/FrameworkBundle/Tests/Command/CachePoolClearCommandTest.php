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
use Symfony\Bundle\FrameworkBundle\Command\CachePoolClearCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\HttpKernel\KernelInterface;

class CachePoolClearCommandTest extends TestCase
{
    private $cachePool;

    protected function setUp(): void
    {
        $this->cachePool = self::createMock(CacheItemPoolInterface::class);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application($this->getKernel());
        $application->add(new CachePoolClearCommand(new Psr6CacheClearer(['foo' => $this->cachePool]), ['foo']));
        $tester = new CommandCompletionTester($application->get('cache:pool:clear'));

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
}
