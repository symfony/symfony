<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapper;
use Symfony\Component\Form\Extension\Validator\EventListener\ValidationListener;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeValidatorExtension extends AbstractTypeExtension
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ViolationMapper
     */
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
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        // BC clause
        $constraints = function (Options $options) {
            return $options['validation_constraint'];
        };

        // Make sure that validation groups end up as null, closure or array
        $validationGroupsNormalizer = function (Options $options, $groups) {
            if (empty($groups)) {
                return null;
            }

            if (is_callable($groups)) {
                return $groups;
            }

            return (array) $groups;
        };

        // Constraint should always be converted to an array
        $constraintsNormalizer = function (Options $options, $constraints) {
            return is_object($constraints) ? array($constraints) : (array) $constraints;
        };

        $resolver->setDefaults(array(
            'error_mapping'              => array(),
            'validation_groups'          => null,
            // "validation_constraint" is deprecated. Use "constraints".
            'validation_constraint'      => null,
            'constraints'                => $constraints,
            'cascade_validation'         => false,
            'invalid_message'            => 'This value is not valid.',
            'invalid_message_parameters' => array(),
            'extra_fields_message'       => 'This form should not contain extra fields.',
            'post_max_size_message'      => 'The uploaded file was too large. Please try to upload a smaller file.',
        ));

        $resolver->setNormalizers(array(
            'validation_groups' => $validationGroupsNormalizer,
            'constraints'       => $constraintsNormalizer,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
