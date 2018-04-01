<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Validator\Type;

use Symphony\Component\Form\FormBuilderInterface;
use Symphony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapper;
use Symphony\Component\Form\Extension\Validator\EventListener\ValidationListener;
use Symphony\Component\Validator\Validator\ValidatorInterface;
use Symphony\Component\OptionsResolver\Options;
use Symphony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeValidatorExtension extends BaseValidatorExtension
{
    private $validator;
    private $violationMapper;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->violationMapper = new ViolationMapper();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new ValidationListener($this->validator, $this->violationMapper));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        // Constraint should always be converted to an array
        $constraintsNormalizer = function (Options $options, $constraints) {
            return is_object($constraints) ? array($constraints) : (array) $constraints;
        };

        $resolver->setDefaults(array(
            'error_mapping' => array(),
            'constraints' => array(),
            'invalid_message' => 'This value is not valid.',
            'invalid_message_parameters' => array(),
            'allow_extra_fields' => false,
            'extra_fields_message' => 'This form should not contain extra fields.',
        ));

        $resolver->setNormalizer('constraints', $constraintsNormalizer);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'Symphony\Component\Form\Extension\Core\Type\FormType';
    }
}
