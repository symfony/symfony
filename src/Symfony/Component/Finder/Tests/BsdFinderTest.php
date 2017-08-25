<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests;

use Symfony\Component\Finder\Adapter\BsdFindAdapter;
use Symfony\Component\Finder\Finder;

/**
 * @group legacy
 */
class BsdFinderTest extends FinderTest
{
    protected function buildFinder()
    {
        $adapter = new BsdFindAdapter();

        if (!$adapter->isSupported()) {
            $this->markTestSkipped(get_class($adapter).' is not supported.');
        }

        return Finder::create()
            ->removeAdapters()
            ->addAdapter($adapter);
    }
}
