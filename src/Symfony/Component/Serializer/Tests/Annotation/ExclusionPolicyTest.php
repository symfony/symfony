<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use Symfony\Component\Serializer\Annotation\ExclusionPolicy;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ExclusionPolicyTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testNoParameter()
    {
        new ExclusionPolicy(array());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameter()
    {
        new ExclusionPolicy(array('value' => 'Foobar'));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameterType()
    {
        new ExclusionPolicy(array('value' => new \stdClass()));
    }

    public function testExclusionPolicy()
    {
        $policy = new ExclusionPolicy(array('value' => 'all'));
        $this->assertEquals(ExclusionPolicy::ALL, $policy->getPolicy());

        $policy = new ExclusionPolicy(array('value' => 'none'));
        $this->assertEquals(ExclusionPolicy::NONE, $policy->getPolicy());
    }
}
