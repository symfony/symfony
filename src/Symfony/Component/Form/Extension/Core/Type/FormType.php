<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Options;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\Extension\Core\EventListener\ValidationListener;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\FormException;

class FormType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
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

        if (!is_array($options['attr'])) {
            throw new FormException('The "attr" option must be an "array".');
        }

        if (!is_array($options['label_attr'])) {
            throw new FormException('The "label_attr" option must be an "array".');
        }

        $builder
            ->setRequired($options['required'])
            ->setDisabled($options['disabled'])
            ->setErrorBubbling($options['error_bubbling'])
            ->setEmptyData($options['empty_data'])
            ->setAttribute('read_only', $options['read_only'])
            ->setAttribute('by_reference', $options['by_reference'])
            ->setAttribute('property_path', $options['property_path'])
            ->setAttribute('error_mapping', $options['error_mapping'])
            ->setAttribute('max_length', $options['max_length'])
            ->setAttribute('pattern', $options['pattern'])
            ->setAttribute('label', $options['label'] ?: $this->humanize($builder->getName()))
            ->setAttribute('attr', $options['attr'])
            ->setAttribute('label_attr', $options['label_attr'])
            ->setAttribute('invalid_message', $options['invalid_message'])
            ->setAttribute('invalid_message_parameters', $options['invalid_message_parameters'])
            ->setAttribute('translation_domain', $options['translation_domain'])
            ->setAttribute('virtual', $options['virtual'])
            ->setAttribute('single_control', $options['single_control'])
            ->setData($options['data'])
            ->setDataMapper(new PropertyPathMapper($options['data_class']))
            ->addEventSubscriber(new ValidationListener())
        ;

        if ($options['trim']) {
            $builder->addEventSubscriber(new TrimListener());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form)
    {
        $name = $form->getName();
        $readOnly = $form->getAttribute('read_only');

        if ($view->hasParent()) {
            if ('' === $name) {
                throw new FormException('Form node with empty name can be used only as root form node.');
            }

            if ('' !== ($parentFullName = $view->getParent()->get('full_name'))) {
                $id = sprintf('%s_%s', $view->getParent()->get('id'), $name);
                $fullName = sprintf('%s[%s]', $parentFullName, $name);
            } else {
                $id = $name;
                $fullName = $name;
            }

            // Complex fields are read-only if themselves or their parent is.
            $readOnly = $readOnly || $view->getParent()->get('read_only');
        } else {
            $id = $name;
            $fullName = $name;

            // Strip leading underscores and digits. These are allowed in
            // form names, but not in HTML4 ID attributes.
            // http://www.w3.org/TR/html401/struct/global.html#adef-id
            $id = ltrim($id, '_0123456789');
        }

        $types = array();
        foreach ($form->getTypes() as $type) {
            $types[] = $type->getName();
        }

        $view
            ->set('form', $view)
            ->set('id', $id)
            ->set('name', $name)
            ->set('full_name', $fullName)
            ->set('read_only', $readOnly)
            ->set('errors', $form->getErrors())
            ->set('value', $form->getClientData())
            ->set('disabled', $form->isDisabled())
            ->set('required', $form->isRequired())
            ->set('max_length', $form->getAttribute('max_length'))
            ->set('pattern', $form->getAttribute('pattern'))
            ->set('size', null)
            ->set('label', $form->getAttribute('label'))
            ->set('multipart', false)
            ->set('attr', $form->getAttribute('attr'))
            ->set('label_attr', $form->getAttribute('label_attr'))
            ->set('single_control', $form->getAttribute('single_control'))
            ->set('types', $types)
            ->set('translation_domain', $form->getAttribute('translation_domain'))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
        $multipart = false;

        foreach ($view->getChildren() as $child) {
            if ($child->get('multipart')) {
                $multipart = true;
                break;
            }
        }

        $view->set('multipart', $multipart);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions()
    {
        // Derive "data_class" option from passed "data" object
        $dataClass = function (Options $options) {
            if (is_object($options['data'])) {
                return get_class($options['data']);
            }

            return null;
        };

        // Derive "empty_data" closure from "data_class" option
        $emptyData = function (Options $options) {
            $class = $options['data_class'];

            if (null !== $class) {
                return function (FormInterface $form) use ($class) {
                    if ($form->isEmpty() && !$form->isRequired()) {
                        return null;
                    }

                    return new $class();
                };
            }

            return function (FormInterface $form) {
                if ($form->hasChildren()) {
                    return array();
                }

                return '';
            };
        };

        // For any form that is not represented by a single HTML control,
        // errors should bubble up by default
        $errorBubbling = function (Options $options) {
            return !$options['single_control'];
        };

        return array(
            'data'              => null,
            'data_class'        => $dataClass,
            'empty_data'        => $emptyData,
            'trim'              => true,
            'required'          => true,
            'read_only'         => false,
            'disabled'          => false,
            'max_length'        => null,
            'pattern'           => null,
            'property_path'     => null,
            'by_reference'      => true,
            'error_bubbling'    => $errorBubbling,
            'error_mapping'     => array(),
            'label'             => null,
            'attr'              => array(),
            'label_attr'        => array(),
            'virtual'           => false,
            'single_control'    => false,
            'invalid_message'   => 'This value is not valid.',
            'invalid_message_parameters' => array(),
            'translation_domain' => 'messages',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return new FormBuilder($name, $factory, new EventDispatcher(), $options['data_class']);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(array $options)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }

    private function humanize($text)
    {
        return ucfirst(trim(strtolower(preg_replace('/[_\s]+/', ' ', $text))));
    }
}
