<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Locale;

use PHPUnit\Framework\TestCase;

/**
 * Test case for Locale implementations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractLocaleTest extends TestCase
{
    public function testSetDefault()
    {
        $this->call('setDefault', 'en_GB');

        $this->assertSame('en_GB', $this->call('getDefault'));
    }

    abstract protected function call($methodName);
}
