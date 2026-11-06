<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class IsCsrfTokenValidTest extends TestCase
{
    public function testCommaSeparatedMethods()
    {
        $this->assertSame(['GET', 'POST', 'HEAD'], (new IsCsrfTokenValid('foo', methods: 'get, POST'))->methods);
    }
}
