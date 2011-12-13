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
use Symfony\Component\Form\Extension\Validator\Validator\DelegatingValidator;
use Symfony\Component\Validator\ValidatorInterface;

class FieldTypeValidatorExtension extends AbstractTypeExtension
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        $options['validation_groups'] = empty($options['validation_groups']) ? null : (array) $options['validation_groups'];

        $builder
            ->setAttribute('validation_groups', $options['validation_groups'])
            ->setAttribute('validation_constraint', $options['validation_constraint'])
            ->addValidator(new DelegatingValidator($this->validator));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'validation_groups' => null,
            'validation_constraint' => null,
        );
    }

    public function getExtendedType()
    {
        return 'field';
    }
}
