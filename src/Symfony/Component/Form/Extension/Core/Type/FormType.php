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

use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class FormType extends BaseType
{
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $isDataOptionSet = array_key_exists('data', $options);

        $builder
            ->setRequired($options['required'])
            ->setErrorBubbling($options['error_bubbling'])
            ->setEmptyData($options['empty_data'])
            ->setPropertyPath($options['property_path'])
            ->setMapped($options['mapped'])
            ->setByReference($options['by_reference'])
            ->setInheritData($options['inherit_data'])
            ->setCompound($options['compound'])
            ->setData($isDataOptionSet ? $options['data'] : null)
            ->setDataLocked($isDataOptionSet)
            ->setDataMapper($options['compound'] ? new PropertyPathMapper($this->propertyAccessor) : null)
            ->setMethod($options['method'])
            ->setAction($options['action']);

        if ($options['trim']) {
            $builder->addEventSubscriber(new TrimListener());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $name = $form->getName();

        if ($view->parent) {
            if ('' === $name) {
                throw new LogicException('Form node with empty name can be used only as root form node.');
            }

            // Complex fields are read-only if they themselves or their parents are.
            if (!isset($view->vars['attr']['readonly']) && isset($view->parent->vars['attr']['readonly']) && false !== $view->parent->vars['attr']['readonly']) {
                $view->vars['attr']['readonly'] = true;
            }
        }

        $formConfig = $form->getConfig();
        $view->vars = array_replace($view->vars, array(
            'read_only' => isset($view->vars['attr']['readonly']) && false !== $view->vars['attr']['readonly'], // deprecated
            'errors' => $form->getErrors(),
            'valid' => $form->isSubmitted() ? $form->isValid() : true,
            'value' => $form->getViewData(),
            'data' => $form->getNormData(),
            'required' => $form->isRequired(),
            'max_length' => isset($options['attr']['maxlength']) ? $options['attr']['maxlength'] : null, // Deprecated
            'pattern' => isset($options['attr']['pattern']) ? $options['attr']['pattern'] : null, // Deprecated
            'size' => null,
            'label_attr' => $options['label_attr'],
            'compound' => $formConfig->getCompound(),
            'method' => $formConfig->getMethod(),
            'action' => $formConfig->getAction(),
            'submitted' => $form->isSubmitted(),
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
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        // Derive "data_class" option from passed "data" object
        $dataClass = function (Options $options) {
            return isset($options['data']) && \is_object($options['data']) ? \get_class($options['data']) : null;
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

        // Wrap "post_max_size_message" in a closure to translate it lazily
        $uploadMaxSizeMessage = function (Options $options) {
            return function () use ($options) {
                return $options['post_max_size_message'];
            };
        };

        // For any form that is not represented by a single HTML control,
        // errors should bubble up by default
        $errorBubbling = function (Options $options) {
            return $options['compound'];
        };

        // BC with old "virtual" option
        $inheritData = function (Options $options) {
            if (null !== $options['virtual']) {
                @trigger_error('The form option "virtual" is deprecated since Symfony 2.3 and will be removed in 3.0. Use "inherit_data" instead.', E_USER_DEPRECATED);

                return $options['virtual'];
            }

            return false;
        };

        // If data is given, the form is locked to that data
        // (independent of its value)
        $resolver->setDefined(array(
            'data',
        ));

        // BC clause for the "max_length" and "pattern" option
        // Add these values to the "attr" option instead
        $defaultAttr = function (Options $options) {
            $attributes = array();

            if (null !== $options['max_length']) {
                $attributes['maxlength'] = $options['max_length'];
            }

            if (null !== $options['pattern']) {
                $attributes['pattern'] = $options['pattern'];
            }

            return $attributes;
        };

        // BC for "read_only" option
        $attrNormalizer = function (Options $options, array $attr) {
            if (!isset($attr['readonly']) && $options['read_only']) {
                $attr['readonly'] = true;
            }

            return $attr;
        };

        $readOnlyNormalizer = function (Options $options, $readOnly) {
            if (null !== $readOnly) {
                @trigger_error('The form option "read_only" is deprecated since Symfony 2.8 and will be removed in 3.0. Use "attr[\'readonly\']" instead.', E_USER_DEPRECATED);

                return $readOnly;
            }

            return false;
        };

        $resolver->setDefaults(array(
            'data_class' => $dataClass,
            'empty_data' => $emptyData,
            'trim' => true,
            'required' => true,
            'read_only' => null, // deprecated
            'max_length' => null,
            'pattern' => null,
            'property_path' => null,
            'mapped' => true,
            'by_reference' => true,
            'error_bubbling' => $errorBubbling,
            'label_attr' => array(),
            'virtual' => null,
            'inherit_data' => $inheritData,
            'compound' => true,
            'method' => 'POST',
            // According to RFC 2396 (http://www.ietf.org/rfc/rfc2396.txt)
            // section 4.2., empty URIs are considered same-document references
            'action' => '',
            'attr' => $defaultAttr,
            'post_max_size_message' => 'The uploaded file was too large. Please try to upload a smaller file.',
            'upload_max_size_message' => $uploadMaxSizeMessage, // internal
        ));

        $resolver->setNormalizer('attr', $attrNormalizer);
        $resolver->setNormalizer('read_only', $readOnlyNormalizer);

        $resolver->setAllowedTypes('label_attr', 'array');
        $resolver->setAllowedTypes('upload_max_size_message', array('callable'));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'form';
    }
}
