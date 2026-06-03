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

use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Tests\Fixtures\FailingTagFilesystemAdapter;

#[Group('time-sensitive')]
class FilesystemTagAwareAdapterTest extends FilesystemAdapterTest
{
    use TagAwareTestTrait;

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new FilesystemTagAwareAdapter('', $defaultLifetime);
    }

    public function testCommitDoesNotFailWhenTagSaveFails()
    {
        $adapter = new FailingTagFilesystemAdapter();

        $item = $adapter->getItem('foo');
        $item->set('bar');
        $item->tag(['tag1']);
        $adapter->saveDeferred($item);

        $this->assertFalse($adapter->commit(), 'Commit should return false when tag save fails');
    }
}
