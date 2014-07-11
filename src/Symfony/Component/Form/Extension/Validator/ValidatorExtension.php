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

use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * Extension supporting the Symfony2 Validator component in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorExtension extends AbstractExtension
{
    private $validator;

    /**
     * @param ValidatorInterface|LegacyValidatorInterface $validator
     */
    public function __construct($validator)
    {
        // since validator apiVersion 2.5
        if ($validator instanceof ValidatorInterface) {
            $this->validator = $validator;

            /** @var $metadata ClassMetadata */
            $metadata = $this->validator->getMetadataFor('Symfony\Component\Form\Form');
        // until validator apiVersion 2.4
        } elseif ($validator instanceof LegacyValidatorInterface) {
            $this->validator = $validator;

            /** @var $metadata ClassMetadata */
            $metadata = $this->validator->getMetadataFactory()->getMetadataFor('Symfony\Component\Form\Form');
        } else {
            throw new \InvalidArgumentException('Validator must be instance of Symfony\Component\Validator\Validator\ValidatorInterface or Symfony\Component\Validator\ValidatorInterface');
        }

        // Register the form constraints in the validator programmatically.
        // This functionality is required when using the Form component without
        // the DIC, where the XML file is loaded automatically. Thus the following
        // code must be kept synchronized with validation.xml

        $metadata->addConstraint(new Form());
        $metadata->addPropertyConstraint('children', new Valid());
    }

    public function loadTypeGuesser()
    {
        return new ValidatorTypeGuesser($this->validator->getMetadataFactory());
    }

    protected function loadTypeExtensions()
    {
        return array(
            new Type\FormTypeValidatorExtension($this->validator),
            new Type\RepeatedTypeValidatorExtension(),
            new Type\SubmitTypeValidatorExtension(),
        );
    }
}
