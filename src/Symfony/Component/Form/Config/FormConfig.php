<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\FieldFactory\FieldFactoryInterface;
use Symfony\Component\Form\Renderer\Plugin\FormPlugin;
use Symfony\Component\Validator\ValidatorInterface;

class FormConfig extends AbstractFieldConfig
{
    private $csrfProvider;

    private $fieldFactory;

    private $validator;

    public function __construct(CsrfProviderInterface $csrfProvider,
            FieldFactoryInterface $fieldFactory,
            ValidatorInterface $validator)
    {
        $this->csrfProvider = $csrfProvider;
        $this->fieldFactory = $fieldFactory;
        $this->validator = $validator;
    }
    public function configure(FieldInterface $field, array $options)
    {
        $field->setDataClass($options['data_class'])
            ->setDataConstructor($options['data_constructor'])
            ->setFieldFactory($options['field_factory'])
            ->setValidationGroups($options['validation_groups'])
            ->setVirtual($options['virtual'])
            ->setValidator($options['validator'])
            ->addRendererPlugin(new FormPlugin($field    ));

        if ($options['csrf_protection']) {
            $field->enableCsrfProtection($options['csrf_provider'], $options['csrf_field_name']);
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'form',
            'data_class' => null,
            'data_constructor' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_provider' => $this->csrfProvider,
            'field_factory' => $this->fieldFactory,
            'validation_groups' => null,
            'virtual' => false,
            'validator' => $this->validator,
        );
    }

    public function getClassName()
    {
        return 'Symfony\Component\Form\Form';
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getIdentifier()
    {
        return 'form';
    }
}