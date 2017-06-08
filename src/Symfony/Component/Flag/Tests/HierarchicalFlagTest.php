<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Flag\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Flag\HierarchicalFlag;
use Symfony\Component\Flag\Tests\Fixtures\Foo;

/**
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class HierarchicalFlagTest extends TestCase
{
    public function testHas()
    {
        $flag = new HierarchicalFlag(Foo::class, 'FLAG_', Foo::FLAG_B);

        $this->assertTrue($flag->has(Foo::FLAG_A));
        $this->assertTrue($flag->has(Foo::FLAG_B));
        $this->assertFalse($flag->has(Foo::FLAG_C));
    }
}
