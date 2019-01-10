<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ServicesResetterTest extends TestCase
{
    protected function setUp()
    {
        ResettableService::$counter = 0;
        ClearableService::$counter = 0;
    }

    public function testResetServices()
    {
        $resetter = new ServicesResetter(new \ArrayIterator([
            'id1' => new ResettableService(),
            'id2' => new ClearableService(),
        ]), [
            'id1' => 'reset',
            'id2' => 'clear',
        ]);

        $resetter->reset();

        $this->assertEquals(1, ResettableService::$counter);
        $this->assertEquals(1, ClearableService::$counter);
    }
}
