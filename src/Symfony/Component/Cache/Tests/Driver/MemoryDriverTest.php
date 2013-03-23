<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Cache\Tests\Driver;

use Symfony\Component\Cache\Driver\MemoryDriver;
use Symfony\Component\Cache\Driver\BatchDriverInterface;
use Symfony\Component\Cache\Tests\Driver\AbstractDriverTest;

class MemoryDriverTest extends AbstractDriverTest
{

    public function _getTestDriver()
    {
        return new MemoryDriver();
    }

}
