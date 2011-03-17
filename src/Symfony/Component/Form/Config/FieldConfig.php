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

use Symfony\Component\Form\Field;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Plugin\FieldPlugin;
use Symfony\Component\Form\EventListener\TrimListener;
use Symfony\Component\Form\EventListener\ValidationListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\ValidatorInterface;

class FieldConfig extends AbstractFieldConfig
{
    private $theme;

    private $validator;

    public function __construct(FormFactoryInterface $factory,
            ThemeInterface $theme, ValidatorInterface $validator)
    {
        parent::__construct($factory);

        $this->theme = $theme;
        $this->validator = $validator;
    }

    public function configure(FieldInterface $field, array $options)
    {
        $field->setPropertyPath($options['property_path'] === false
                    ? $field->getName()
                    : $options['property_path'])
            ->setRequired($options['required'])
            ->setDisabled($options['disabled'])
            ->setValueTransformer($options['value_transformer'])
            ->setNormalizationTransformer($options['normalization_transformer'])
            ->addEventSubscriber(new ValidationListener($this->validator), -128)
            ->setData($options['data'])
            ->setRenderer(new DefaultRenderer($field, $this->theme, $options['template']))
            ->addRendererPlugin(new FieldPlugin());

        if ($options['trim']) {
            $field->addEventSubscriber(new TrimListener());
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'text',
            'data' => null,
            'property_path' => false,
            'trim' => true,
            'required' => true,
            'disabled' => false,
            'value_transformer' => null,
            'normalization_transformer' => null,
        );
    }

    public function createInstance($name)
    {
        return new Field($name, new EventDispatcher());
    }

    public function getParent(array $options)
    {
        return null;
    }

    public function getIdentifier()
    {
        return 'field';
    }
}