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
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapper;
use Symfony\Component\Form\Extension\Validator\EventListener\ValidationListener;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\OptionsResolver\Options;

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
    public function buildForm(FormBuilder $builder, array $options)
    {
        if (empty($options['validation_groups'])) {
            $options['validation_groups'] = null;
        } else {
            $options['validation_groups'] = is_callable($options['validation_groups'])
                ? $options['validation_groups']
                : (array) $options['validation_groups'];
        }

        // Objects, when casted to an array, are split into their properties
        $constraints = is_object($options['constraints'])
            ? array($options['constraints'])
            : (array) $options['constraints'];

        $builder
            ->setAttribute('error_mapping', $options['error_mapping'])
            ->setAttribute('validation_groups', $options['validation_groups'])
            ->setAttribute('constraints', $constraints)
            ->setAttribute('cascade_validation', $options['cascade_validation'])
            ->setAttribute('invalid_message', $options['invalid_message'])
            ->setAttribute('extra_fields_message', $options['extra_fields_message'])
            ->setAttribute('post_max_size_message', $options['post_max_size_message'])
            ->addEventSubscriber(new ValidationListener($this->validator, $this->violationMapper))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions()
    {
        // BC clause
        $constraints = function (Options $options) {
            return $options['validation_constraint'];
        };

        return array(
            'error_mapping'         => array(),
            'validation_groups'     => null,
            // "validation_constraint" is deprecated. Use "constraints".
            'validation_constraint' => null,
            'constraints'           => $constraints,
            'cascade_validation'    => false,
            'invalid_message'       => 'This value is not valid.',
            'extra_fields_message'  => 'This form should not contain extra fields.',
            'post_max_size_message' => 'The uploaded file was too large. Please try to upload a smaller file.',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
