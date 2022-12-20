<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Test\Traits;

use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait ValidatorExtensionTrait
{
    /**
     * @var ValidatorInterface|null
     */
    protected $validator;

    protected function getValidatorExtension(): ValidatorExtension
    {
        if (!interface_exists(ValidatorInterface::class)) {
            throw new \Exception('In order to use the "ValidatorExtensionTrait", the symfony/validator component must be installed.');
        }

        if (!$this instanceof TypeTestCase) {
            throw new \Exception(sprintf('The trait "ValidatorExtensionTrait" can only be added to a class that extends "%s".', TypeTestCase::class));
        }

        $this->validator = self::createMock(ValidatorInterface::class);
        $metadata = self::getMockBuilder(ClassMetadata::class)->setConstructorArgs([''])->setMethods(['addPropertyConstraint'])->getMock();
        $this->validator->expects(self::any())->method('getMetadataFor')->will(self::returnValue($metadata));
        $this->validator->expects(self::any())->method('validate')->will(self::returnValue(new ConstraintViolationList()));

        return new ValidatorExtension($this->validator, false);
    }
}
