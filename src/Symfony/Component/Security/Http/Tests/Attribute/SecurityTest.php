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
use Symfony\Component\Security\Http\Attribute\Security;

class SecurityTest extends TestCase
{
    public function testEmptyConstruct()
    {
        $security = new Security();
        $this->assertEquals('Access denied.', $security->getMessage());
        $this->assertNull($security->getStatusCode());
        $this->assertNull($security->getExpression());
    }

    public function testSettersViaConstruct()
    {
        $security = new Security("is_granted('foo')", 'Not allowed', 403);
        $this->assertEquals('Not allowed', $security->getMessage());
        $this->assertEquals(403, $security->getStatusCode());
        $this->assertEquals("is_granted('foo')", $security->getExpression());
    }

    public function testSetters()
    {
        $security = new Security("is_granted('foo')", 'Not allowed', 403);
        $security->setExpression("is_granted('bar')");
        $security->setMessage('Disallowed');
        $security->setStatusCode(404);
        $this->assertEquals('Disallowed', $security->getMessage());
        $this->assertEquals(404, $security->getStatusCode());
        $this->assertEquals("is_granted('bar')", $security->getExpression());
    }
}
