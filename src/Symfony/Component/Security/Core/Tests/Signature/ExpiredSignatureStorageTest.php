<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Core\Signature\ExpiredSignatureStorage;

class ExpiredSignatureStorageTest extends TestCase
{
    public function testUsage()
    {
        $cache = new ArrayAdapter();
        $storage = new ExpiredSignatureStorage($cache, 600);

        $this->assertSame(0, $storage->countUsages('hash+more'));
        $storage->incrementUsages('hash+more');
        $this->assertSame(1, $storage->countUsages('hash+more'));
    }
}
