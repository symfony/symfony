<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Templating\Tests\Fixtures\TestStorage;

/**
 * @group legacy
 */
class StorageTest extends TestCase
{
    public function testMagicToString()
    {
        $storage = new TestStorage('foo');
        $this->assertEquals('foo', (string) $storage, '__toString() returns the template name');
    }
}
