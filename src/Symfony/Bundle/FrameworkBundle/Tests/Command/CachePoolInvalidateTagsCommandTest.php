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

use Symfony\Bundle\FrameworkBundle\Command\CachePoolInvalidateTagsCommand;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CachePoolInvalidateTagsCommandTest extends TestCase
{
    public function testComplete()
    {
        $tester = new CommandCompletionTester($this->createCommand(['foo' => null, 'bar' => null]));

        $suggestions = $tester->complete(['--pool=']);

        $this->assertSame(['foo', 'bar'], $suggestions);
    }

    public function testInvalidatesTagsForAllPoolsByDefault()
    {
        $tagsToInvalidate = ['tag1', 'tag2'];

        $foo = $this->createMock(TagAwareCacheInterface::class);
        $foo->expects($this->once())->method('invalidateTags')->with($tagsToInvalidate)->willReturn(true);

        $bar = $this->createMock(TagAwareCacheInterface::class);
        $bar->expects($this->once())->method('invalidateTags')->with($tagsToInvalidate)->willReturn(true);

        $tester = new CommandTester($this->createCommand([
            'foo' => $foo,
            'bar' => $bar,
        ]));

        $ret = $tester->execute(['tags' => $tagsToInvalidate]);

        $this->assertSame(Command::SUCCESS, $ret);
    }

    public function testCanInvalidateSpecificPools()
    {
        $tagsToInvalidate = ['tag1', 'tag2'];

        $foo = $this->createMock(TagAwareCacheInterface::class);
        $foo->expects($this->once())->method('invalidateTags')->with($tagsToInvalidate)->willReturn(true);

        $bar = $this->createMock(TagAwareCacheInterface::class);
        $bar->expects($this->never())->method('invalidateTags');

        $tester = new CommandTester($this->createCommand([
            'foo' => $foo,
            'bar' => $bar,
        ]));

        $ret = $tester->execute(['tags' => $tagsToInvalidate, '--pool' => ['foo']]);

        $this->assertSame(Command::SUCCESS, $ret);
    }

    public function testCommandFailsIfPoolNotFound()
    {
        $tagsToInvalidate = ['tag1', 'tag2'];

        $foo = $this->createMock(TagAwareCacheInterface::class);
        $foo->expects($this->once())->method('invalidateTags')->with($tagsToInvalidate)->willReturn(true);

        $bar = $this->createMock(TagAwareCacheInterface::class);
        $bar->expects($this->never())->method('invalidateTags');

        $tester = new CommandTester($this->createCommand([
            'foo' => $foo,
            'bar' => $bar,
        ]));

        $ret = $tester->execute(['tags' => $tagsToInvalidate, '--pool' => ['invalid', 'foo']]);

        $this->assertSame(Command::FAILURE, $ret);
    }

    public function testCommandFailsIfPoolNotTaggable()
    {
        $tagsToInvalidate = ['tag1', 'tag2'];

        $foo = new \stdClass();

        $bar = $this->createMock(TagAwareCacheInterface::class);
        $bar->expects($this->once())->method('invalidateTags')->with($tagsToInvalidate)->willReturn(true);

        $tester = new CommandTester($this->createCommand([
            'foo' => $foo,
            'bar' => $bar,
        ]));

        $ret = $tester->execute(['tags' => $tagsToInvalidate]);

        $this->assertSame(Command::FAILURE, $ret);
    }

    public function testCommandFailsIfInvalidatingTagsFails()
    {
        $tagsToInvalidate = ['tag1', 'tag2'];

        $foo = $this->createMock(TagAwareCacheInterface::class);
        $foo->expects($this->once())->method('invalidateTags')->with($tagsToInvalidate)->willReturn(false);

        $bar = $this->createMock(TagAwareCacheInterface::class);
        $bar->expects($this->once())->method('invalidateTags')->with($tagsToInvalidate)->willReturn(true);

        $tester = new CommandTester($this->createCommand([
            'foo' => $foo,
            'bar' => $bar,
        ]));

        $ret = $tester->execute(['tags' => $tagsToInvalidate]);

        $this->assertSame(Command::FAILURE, $ret);
    }

    private function createCommand(array $services): CachePoolInvalidateTagsCommand
    {
        return new CachePoolInvalidateTagsCommand(
            new ServiceLocator(array_map(fn ($service) => fn () => $service, $services))
        );
    }
}
