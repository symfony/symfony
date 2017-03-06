<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
        $this->validator = $this->getMockBuilder('Symfony\Component\Validator\Validator\ValidatorInterface')->getMock();
        $metadata = $this->getMockBuilder('Symfony\Component\Validator\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $this->validator->expects($this->once())->method('getMetadataFor')->will($this->returnValue($metadata));

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
