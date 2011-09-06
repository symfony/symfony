<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\ClassIdentity;
use Doctrine\ORM\Proxy\Proxy;

class ClassIdentityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getCompareData
     */
    public function testEquals($cls1, $cls2)
    {
        $this->assertEquals($cls1, $cls2);
    }

    public function getCompareData()
    {
        return array(
            array('Symfony\Tests\Component\Security\Acl\Domain\TestObjectClassIdentity', ClassIdentity::getClass(new TestObjectClassIdentity())),
            array('Symfony\Tests\Component\Security\Acl\Domain\TestObjectClassIdentity', ClassIdentity::getClass(new TestObjectClassIdentityProxy())),
        );
    }

    public function setUp()
    {
        if (!interface_exists('Doctrine\ORM\Proxy\Proxy')) {
            $this->markTestSkipped('The Doctrine2 ORM is required for this test');
        }
    }
}

class TestObjectClassIdentity { }

class TestObjectClassIdentityProxy extends TestObjectClassIdentity implements Proxy { }
