<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Validator;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\Extension\Validator\ValidatorExtension;

class ValidatorExtensionTest extends TestCase
{
    public function test2Dot5ValidationApi()
    {
        $validator = $this->getMockBuilder('Symphony\Component\Validator\Validator\RecursiveValidator')
            ->disableOriginalConstructor()
            ->getMock();
        $metadata = $this->getMockBuilder('Symphony\Component\Validator\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $validator->expects($this->once())
            ->method('getMetadataFor')
            ->with($this->identicalTo('Symphony\Component\Form\Form'))
            ->will($this->returnValue($metadata));

        // Verify that the constraints are added
        $metadata->expects($this->once())
            ->method('addConstraint')
            ->with($this->isInstanceOf('Symphony\Component\Form\Extension\Validator\Constraints\Form'));

        $metadata->expects($this->once())
            ->method('addPropertyConstraint')
            ->with('children', $this->isInstanceOf('Symphony\Component\Validator\Constraints\Valid'));

        $extension = new ValidatorExtension($validator);
        $guesser = $extension->loadTypeGuesser();

        $this->assertInstanceOf('Symphony\Component\Form\Extension\Validator\ValidatorTypeGuesser', $guesser);
    }
}
