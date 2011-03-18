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
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Plugin\FieldPlugin;
use Symfony\Component\Form\EventListener\TrimListener;
use Symfony\Component\Form\EventListener\ValidationListener;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\DataValidator\DelegatingValidator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\ValidatorInterface;

class FieldConfig extends AbstractFieldConfig
{
    private $csrfProvider;

    private $theme;

    private $validator;

    public function __construct(CsrfProviderInterface $csrfProvider,
            ThemeInterface $theme, ValidatorInterface $validator)
    {
        $this->csrfProvider = $csrfProvider;
        $this->theme = $theme;
        $this->validator = $validator;
    }

    public function configure(FieldBuilder $builder, array $options)
    {
        if (false === $options['property_path']) {
            $options['property_path'] = $builder->getName();
        }

        if (null === $options['property_path'] || '' === $options['property_path']) {
            $options['property_path'] = null;
        } else {
            $options['property_path'] = new PropertyPath($options['property_path']);
        }

        $options['validation_groups'] = empty($options['validation_groups'])
            ? null
            : (array)$options['validation_groups'];

        $builder->setRequired($options['required'])
            ->setDisabled($options['disabled'])
            ->setAttribute('by_reference', $options['by_reference'])
            ->setAttribute('property_path', $options['property_path'])
            ->setAttribute('validation_groups', $options['validation_groups'])
            ->setDataTransformer($options['value_transformer'])
            ->setNormalizationTransformer($options['normalization_transformer'])
            ->setData($options['data'])
            ->setRenderer(new DefaultRenderer($this->theme, $options['template']))
            ->addRendererPlugin(new FieldPlugin())
            ->setDataValidator(new DelegatingValidator($this->validator));

        if ($options['trim']) {
            $builder->addEventSubscriber(new TrimListener());
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'text',
            'data' => null,
            'trim' => true,
            'required' => true,
            'disabled' => false,
            'value_transformer' => null,
            'normalization_transformer' => null,
            'max_length' => null,
            'property_path' => false,
            'by_reference' => true,
            'validation_groups' => true,
        );
    }

    public function createBuilder(array $options)
    {
        return new FieldBuilder($this->theme, new EventDispatcher(), $this->csrfProvider);
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