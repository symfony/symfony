<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\ThemeRendererInterface;
use Symfony\Component\Form\EventListener\TrimListener;
use Symfony\Component\Form\Validator\DefaultValidator;
use Symfony\Component\Form\Validator\DelegatingValidator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\ValidatorInterface;

class FieldType extends AbstractType
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        if (null === $options['property_path']) {
            $options['property_path'] = $builder->getName();
        }

        if (false === $options['property_path'] || '' === $options['property_path']) {
            $options['property_path'] = null;
        } else {
            $options['property_path'] = new PropertyPath($options['property_path']);
        }

        $options['validation_groups'] = empty($options['validation_groups'])
            ? null
            : (array)$options['validation_groups'];

        $builder->setRequired($options['required'])
            ->setReadOnly($options['read_only'])
            ->setErrorBubbling($options['error_bubbling'])
            ->setEmptyData($options['empty_data'])
            ->setAttribute('by_reference', $options['by_reference'])
            ->setAttribute('property_path', $options['property_path'])
            ->setAttribute('validation_groups', $options['validation_groups'])
            ->setAttribute('error_mapping', $options['error_mapping'])
            ->setData($options['data'])
            ->addValidator(new DefaultValidator())
            ->addValidator(new DelegatingValidator($this->validator));

        if ($options['trim']) {
            $builder->addEventSubscriber(new TrimListener());
        }
    }

    public function buildRenderer(ThemeRendererInterface $renderer, FormInterface $form)
    {
        if ($renderer->hasParent()) {
            $parentId = $renderer->getParent()->getVar('id');
            $parentName = $renderer->getParent()->getVar('name');
            $id = sprintf('%s_%s', $parentId, $form->getName());
            $name = sprintf('%s[%s]', $parentName, $form->getName());
        } else {
            $id = $form->getName();
            $name = $form->getName();
        }

        $renderer->setVar('renderer', $renderer);
        $renderer->setVar('id', $id);
        $renderer->setVar('name', $name);
        $renderer->setVar('errors', $form->getErrors());
        $renderer->setVar('value', $form->getClientData());
        $renderer->setVar('disabled', $form->isReadOnly());
        $renderer->setVar('required', $form->isRequired());
        $renderer->setVar('class', null);
        $renderer->setVar('max_length', null);
        $renderer->setVar('size', null);
        $renderer->setVar('label', ucfirst(strtolower(str_replace('_', ' ', $form->getName()))));
        $renderer->setVar('multipart', false);
        $renderer->setVar('attr', array());
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'data' => null,
            'data_class' => null,
            'trim' => true,
            'required' => true,
            'read_only' => false,
            'max_length' => null,
            'property_path' => null,
            'by_reference' => true,
            'validation_groups' => null,
            'error_bubbling' => false,
            'error_mapping' => array(),
        );

        if (!empty($options['data_class'])) {
            $class = $options['data_class'];
            $defaultOptions['empty_data'] = function () use ($class) {
                return new $class();
            };
        } else {
            $defaultOptions['empty_data'] = null;
        }

        return $defaultOptions;
    }

    public function createBuilder($name, array $options)
    {
        return new FormBuilder($name, new EventDispatcher(), $options['data_class']);
    }

    public function getParent(array $options)
    {
        return null;
    }

    public function getName()
    {
        return 'field';
    }
}