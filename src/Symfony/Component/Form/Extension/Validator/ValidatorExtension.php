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

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Extension supporting the Symfony Validator component in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorExtension extends AbstractExtension
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $metadata = $validator->getMetadataFor('Symfony\Component\Form\Form');

        // Register the form constraints in the validator programmatically.
        // This functionality is required when using the Form component without
        // the DIC, where the XML file is loaded automatically. Thus the following
        // code must be kept synchronized with validation.xml

        /* @var $metadata ClassMetadata */
        $metadata->addConstraint(new Form());
        $metadata->addConstraint(new Traverse(false));

        $this->validator = $validator;
    }

    public function loadTypeGuesser()
    {
        return new ValidatorTypeGuesser($this->validator);
    }

    protected function loadTypeExtensions()
    {
        return [
            new Type\FormTypeValidatorExtension($this->validator),
            new Type\RepeatedTypeValidatorExtension(),
            new Type\SubmitTypeValidatorExtension(),
        ];
    }
}
