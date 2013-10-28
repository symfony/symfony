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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Exception\Exception;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class FormType extends AbstractType
{
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::getPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isDataOptionSet = array_key_exists('data', $options);

        $builder
            ->setRequired($options['required'])
            ->setDisabled($options['disabled'])
            ->setErrorBubbling($options['error_bubbling'])
            ->setEmptyData($options['empty_data'])
            // BC compatibility, when "property_path" could be false
            ->setPropertyPath(is_string($options['property_path']) ? $options['property_path'] : null)
            ->setMapped($options['mapped'])
            ->setByReference($options['by_reference'])
            ->setVirtual($options['virtual'])
            ->setCompound($options['compound'])
            ->setData($isDataOptionSet ? $options['data'] : null)
            ->setDataLocked($isDataOptionSet)
            ->setDataMapper($options['compound'] ? new PropertyPathMapper($this->propertyAccessor) : null)
        ;

        if (false === $options['property_path']) {
            trigger_error('Setting "property_path" to "false" is deprecated since version 2.1 and will be removed in 2.3. Set "mapped" to "false" instead.', E_USER_DEPRECATED);
        }

        if ($options['trim']) {
            $builder->addEventSubscriber(new TrimListener());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $name = $form->getName();
        $blockName = $options['block_name'] ?: $form->getName();
        $readOnly = $options['read_only'];
        $translationDomain = $options['translation_domain'];

        if ($view->parent) {
            if ('' === $name) {
                throw new Exception('Form node with empty name can be used only as root form node.');
            }

            if ('' !== ($parentFullName = $view->parent->vars['full_name'])) {
                $id = sprintf('%s_%s', $view->parent->vars['id'], $name);
                $fullName = sprintf('%s[%s]', $parentFullName, $name);
                $uniqueBlockPrefix = sprintf('%s_%s', $view->parent->vars['unique_block_prefix'], $blockName);
            } else {
                $id = $name;
                $fullName = $name;
                $uniqueBlockPrefix = '_' . $blockName;
            }

            // Complex fields are read-only if they themselves or their parents are.
            if (!$readOnly) {
                $readOnly = $view->parent->vars['read_only'];
            }

            if (!$translationDomain) {
                $translationDomain = $view->parent->vars['translation_domain'];
            }
        } else {
            $id = $name;
            $fullName = $name;
            $uniqueBlockPrefix = '_' . $blockName;

            // Strip leading underscores and digits. These are allowed in
            // form names, but not in HTML4 ID attributes.
            // http://www.w3.org/TR/html401/struct/global.html#adef-id
            $id = ltrim($id, '_0123456789');
        }

        $blockPrefixes = array();
        for ($type = $form->getConfig()->getType(); null !== $type; $type = $type->getParent()) {
            array_unshift($blockPrefixes, $type->getName());
        }
        $blockPrefixes[] = $uniqueBlockPrefix;

        if (!$translationDomain) {
            $translationDomain = 'messages';
        }

        $view->vars = array_replace($view->vars, array(
            'form'                => $view,
            'id'                  => $id,
            'name'                => $name,
            'full_name'           => $fullName,
            'read_only'           => $readOnly,
            'errors'              => $form->getErrors(),
            'valid'               => $form->isBound() ? $form->isValid() : true,
            'value'               => $form->getViewData(),
            'data'                => $form->getNormData(),
            'disabled'            => $form->isDisabled(),
            'required'            => $form->isRequired(),
            'max_length'          => $options['max_length'],
            'pattern'             => $options['pattern'],
            'size'                => null,
            'label'               => $options['label'],
            'multipart'           => false,
            'attr'                => $options['attr'],
            'label_attr'          => $options['label_attr'],
            'compound'            => $form->getConfig()->getCompound(),
            'block_prefixes'      => $blockPrefixes,
            'unique_block_prefix' => $uniqueBlockPrefix,
            'translation_domain'  => $translationDomain,
            // Using the block name here speeds up performance in collection
            // forms, where each entry has the same full block name.
            // Including the type is important too, because if rows of a
            // collection form have different types (dynamically), they should
            // be rendered differently.
            // https://github.com/symfony/symfony/issues/5038
            'cache_key'           => $uniqueBlockPrefix . '_' . $form->getConfig()->getType()->getName(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $multipart = false;

        foreach ($view->children as $child) {
            if ($child->vars['multipart']) {
                $multipart = true;
                break;
            }
        }

        $view->vars['multipart'] = $multipart;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        // Derive "data_class" option from passed "data" object
        $dataClass = function (Options $options) {
            return isset($options['data']) && is_object($options['data']) ? get_class($options['data']) : null;
        };

        // Derive "empty_data" closure from "data_class" option
        $emptyData = function (Options $options) {
            $class = $options['data_class'];

            if (null !== $class) {
                return function (FormInterface $form) use ($class) {
                    return $form->isEmpty() && !$form->isRequired() ? null : new $class();
                };
            }

            return function (FormInterface $form) {
                return $form->getConfig()->getCompound() ? array() : '';
            };
        };

        // For any form that is not represented by a single HTML control,
        // errors should bubble up by default
        $errorBubbling = function (Options $options) {
            return $options['compound'];
        };

        // BC clause: former property_path=false now equals mapped=false
        $mapped = function (Options $options) {
            return false !== $options['property_path'];
        };

        // If data is given, the form is locked to that data
        // (independent of its value)
        $resolver->setOptional(array(
            'data',
        ));

        $resolver->setDefaults(array(
            'block_name'         => null,
            'data_class'         => $dataClass,
            'empty_data'         => $emptyData,
            'trim'               => true,
            'required'           => true,
            'read_only'          => false,
            'disabled'           => false,
            'max_length'         => null,
            'pattern'            => null,
            'property_path'      => null,
            'mapped'             => $mapped,
            'by_reference'       => true,
            'error_bubbling'     => $errorBubbling,
            'label'              => null,
            'attr'               => array(),
            'label_attr'         => array(),
            'virtual'            => false,
            'compound'           => true,
            'translation_domain' => null,
        ));

        $resolver->setAllowedTypes(array(
            'attr'       => 'array',
            'label_attr' => 'array',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
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
}
