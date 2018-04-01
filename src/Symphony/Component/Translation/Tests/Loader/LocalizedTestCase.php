<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;

abstract class LocalizedTestCase extends TestCase
{
    protected function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }
    }
}
