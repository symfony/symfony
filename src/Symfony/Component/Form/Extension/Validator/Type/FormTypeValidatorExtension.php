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
use Symfony\Component\Form\Extension\Validator\EventListener\DelegatingValidationListener;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeValidatorExtension extends AbstractTypeExtension
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        if (empty($options['validation_groups'])) {
            $options['validation_groups'] = null;
        } else {
            $options['validation_groups'] = is_callable($options['validation_groups'])
                ? $options['validation_groups']
                : (array) $options['validation_groups'];
        }

        $builder
            ->setAttribute('validation_groups', $options['validation_groups'])
            ->setAttribute('validation_constraint', $options['validation_constraint'])
            ->setAttribute('cascade_validation', $options['cascade_validation'])
            ->addEventSubscriber(new DelegatingValidationListener($this->validator))
        ;
    }

    public function getDefaultOptions()
    {
        return array(
            'validation_groups' => null,
            'validation_constraint' => null,
            'cascade_validation' => false,
        );
    }

    public function getExtendedType()
    {
        return 'form';
    }
}
