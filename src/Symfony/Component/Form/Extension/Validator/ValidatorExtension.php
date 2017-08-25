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

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * Extension supporting the Symfony Validator component in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorExtension extends AbstractExtension
{
    private $validator;

    /**
     * @param ValidatorInterface|LegacyValidatorInterface $validator
     *
     * @throws UnexpectedTypeException If $validator is invalid
     */
    public function __construct($validator)
    {
        // 2.5 API
        if ($validator instanceof ValidatorInterface) {
            $metadata = $validator->getMetadataFor('Symfony\Component\Form\Form');
        // 2.4 API
        } elseif ($validator instanceof LegacyValidatorInterface) {
            @trigger_error('Passing an instance of Symfony\Component\Validator\ValidatorInterface as argument to the '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use an implementation of Symfony\Component\Validator\Validator\ValidatorInterface instead', E_USER_DEPRECATED);
            $metadata = $validator->getMetadataFactory()->getMetadataFor('Symfony\Component\Form\Form');
        } else {
            throw new UnexpectedTypeException($validator, 'Symfony\Component\Validator\Validator\ValidatorInterface or Symfony\Component\Validator\ValidatorInterface');
        }

        // Register the form constraints in the validator programmatically.
        // This functionality is required when using the Form component without
        // the DIC, where the XML file is loaded automatically. Thus the following
        // code must be kept synchronized with validation.xml

        /* @var $metadata ClassMetadata */
        $metadata->addConstraint(new Form());
        $metadata->addPropertyConstraint('children', new Valid());

        $this->validator = $validator;
    }

    public function loadTypeGuesser()
    {
        // 2.5 API
        if ($this->validator instanceof ValidatorInterface) {
            return new ValidatorTypeGuesser($this->validator);
        }

        // 2.4 API
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
