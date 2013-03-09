<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Type;

use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase as BaseTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

abstract class TypeTestCase extends BaseTestCase
{
    protected $validator;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Validator\Constraint')) {
            $this->markTestSkipped('The "Validator" component is not available');
        }

        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');
        $this->validator->expects($this->once())->method('getMetadataFactory')->will($this->returnValue($metadataFactory));
        $metadata = $this->getMockBuilder('Symfony\Component\Validator\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $metadataFactory->expects($this->once())->method('getMetadataFor')->will($this->returnValue($metadata));

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->validator = null;

        parent::tearDown();
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new ValidatorExtension($this->validator),
        ));
    }
}
