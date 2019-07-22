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

use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Tests\Traits\TagAwareTestTrait;

/**
 * @group time-sensitive
 */
class FilesystemTagAwareAdapterTest extends FilesystemAdapterTest
{
    use TagAwareTestTrait;

    public function createCachePool($defaultLifetime = 0)
    {
        return new FilesystemTagAwareAdapter('', $defaultLifetime);
    }
}
