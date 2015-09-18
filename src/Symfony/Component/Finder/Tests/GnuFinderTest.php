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

use Symfony\Component\Finder\Adapter\GnuFindAdapter;

class GnuFinderTest extends FinderTest
{
    protected function getAdapter()
    {
        $adapter = new GnuFindAdapter();

        if (!$adapter->isSupported()) {
            $this->markTestSkipped(get_class($adapter).' is not supported.');
        }

        return $adapter;
    }
}
