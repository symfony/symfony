<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use Symfony\Component\Intl\Intl;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IntlTest extends \PHPUnit_Framework_TestCase
{
    public function testGetFallbackLocale()
    {
        $this->assertSame('fr', Intl::getFallbackLocale('fr_FR'));
    }

    public function testGetFallbackLocaleForTopLevelLocale()
    {
        $this->assertSame('root', Intl::getFallbackLocale('en'));
    }

    public function testGetFallbackLocaleForRoot()
    {
        $this->assertNull(Intl::getFallbackLocale('root'));
    }
}
