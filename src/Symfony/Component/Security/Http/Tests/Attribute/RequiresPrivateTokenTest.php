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
use Symfony\Component\Security\Http\Attribute\RequiresPrivateToken;

class RequiresPrivateTokenTest extends TestCase
{
    public function testDefaults()
    {
        $attribute = new RequiresPrivateToken();

        $this->assertSame('default', $attribute->name);
        $this->assertSame([], $attribute->methods);
        $this->assertFalse($attribute->optional);
    }

    public function testMethodsAreNormalized()
    {
        $attribute = new RequiresPrivateToken('contact', ['post', 'get']);

        $this->assertSame('contact', $attribute->name);
        $this->assertSame(['POST', 'GET', 'HEAD'], $attribute->methods);
    }

    public function testTheTokenCanBeOptional()
    {
        $this->assertTrue((new RequiresPrivateToken(optional: true))->optional);
    }

    public function testASingleMethodIsAccepted()
    {
        $this->assertSame(['POST'], (new RequiresPrivateToken(methods: 'post'))->methods);
    }
}
