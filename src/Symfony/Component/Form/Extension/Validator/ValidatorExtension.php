<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator;

use Symfony\Component\Form\Extension\Validator\Type;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Valid;

class ValidatorExtension extends AbstractExtension
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;

        $metadata = $this->validator->getMetadataFactory()->getClassMetadata('Symfony\Component\Form\Form');
        $metadata->addConstraint(new Callback(array(array('Symfony\Component\Form\Extension\Validator\Validator\DelegatingValidator', 'validateFormData'))));
        $metadata->addPropertyConstraint('children', new Valid());
    }

    public function loadTypeGuesser()
    {
        return new ValidatorTypeGuesser($this->validator->getMetadataFactory());
    }

    protected function loadTypeExtensions()
    {
        return array(
            new Type\FieldTypeValidatorExtension($this->validator),
        );
    }
}
