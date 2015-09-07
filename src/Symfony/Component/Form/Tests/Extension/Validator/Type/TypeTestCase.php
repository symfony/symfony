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

use Symfony\Component\Form\Test\TypeTestCase as BaseTypeTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

abstract class TypeTestCase extends BaseTypeTestCase
{
    protected $validator;

    protected function setUp()
    {
        $metadata = $this->getMockBuilder('Symfony\Component\Validator\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->expects($this->once())
            ->method('addConstraint')
            ->willReturn(null);

        $this->validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');
        $this->validator->expects($this->once())
            ->method('getMetadataFor')
            ->willReturn($metadata);

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
