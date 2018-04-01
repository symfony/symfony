<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symphony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symphony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ServicesResetterTest extends TestCase
{
    protected function setUp()
    {
        ResettableService::$counter = 0;
        ClearableService::$counter = 0;
    }

    public function testResetServices()
    {
        $resetter = new ServicesResetter(new \ArrayIterator(array(
            'id1' => new ResettableService(),
            'id2' => new ClearableService(),
        )), array(
            'id1' => 'reset',
            'id2' => 'clear',
        ));

        $resetter->reset();

        $this->assertEquals(1, ResettableService::$counter);
        $this->assertEquals(1, ClearableService::$counter);
    }
}
