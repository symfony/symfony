<?php

/*
* This file is part of the Symfony package.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Component\Form\Tests\Extension\Validator;

use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

class ValidatorExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testValidatorInterfaceSinceSymfony25()
    {
        $classMetaData = $this->createClassMetaDataMock();

        // Mock of ValidatorInterface since apiVersion 2.5
        $validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');

        $validator
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with($this->identicalTo('Symfony\Component\Form\Form'))
            ->will($this->returnValue($classMetaData))
        ;

        $validatorExtension = new ValidatorExtension($validator);
        $this->assertAttributeSame($validator, 'validator', $validatorExtension);
    }

    public function testValidatorInterfaceUntilSymfony24()
    {
        $classMetaData = $this->createClassMetaDataMock();

        $metaDataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');

        $metaDataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with($this->identicalTo('Symfony\Component\Form\Form'))
            ->will($this->returnValue($classMetaData))
        ;

        // Mock of ValidatorInterface until apiVersion 2.4
        $validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');

        $validator
            ->expects($this->once())
            ->method('getMetadataFactory')
            ->will($this->returnValue($metaDataFactory))
        ;

        $validatorExtension = new ValidatorExtension($validator);
        $this->assertAttributeSame($validator, 'validator', $validatorExtension);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidValidatorInterface()
    {
        new ValidatorExtension(null);
    }

    /**
     * @return mixed
     */
    private function createClassMetaDataMock()
    {
        $classMetaData = $this->getMockBuilder('Symfony\Component\Validator\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $classMetaData
            ->expects($this->once())
            ->method('addConstraint')
            ->with($this->isInstanceOf('Symfony\Component\Form\Extension\Validator\Constraints\Form'));
        $classMetaData
            ->expects($this->once())
            ->method('addPropertyConstraint')
            ->with(
                $this->identicalTo('children'),
                $this->isInstanceOf('Symfony\Component\Validator\Constraints\Valid')
            );

        return $classMetaData;
    }
}
