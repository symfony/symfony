<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;

#[RequiresPhp('>=8.4.0')]
#[RunTestsInSeparateProcesses]
class ChainAdapterLazyGhostTest extends TestCase
{
    public function testSave()
    {
        $cache = $this->createLazyGhost();

        $this->assertTrue($cache->save((new ArrayAdapter())->getItem('foo')->set('bar')));
        $this->assertSame('bar', $cache->getItem('foo')->get());
    }

    public function testSaveDeferred()
    {
        $cache = $this->createLazyGhost();

        $this->assertTrue($cache->saveDeferred((new ArrayAdapter())->getItem('foo')->set('bar')));
        $this->assertTrue($cache->commit());
        $this->assertSame('bar', $cache->getItem('foo')->get());
    }

    public function testGetItem()
    {
        $cache = $this->createLazyGhost();

        $this->assertFalse($cache->getItem('foo')->isHit());
    }

    private function createLazyGhost(): ChainAdapter
    {
        return (new \ReflectionClass(ChainAdapter::class))->newLazyGhost(static function (ChainAdapter $ghost) {
            $ghost->__construct([new ArrayAdapter(), new ArrayAdapter()]);
        });
    }
}
