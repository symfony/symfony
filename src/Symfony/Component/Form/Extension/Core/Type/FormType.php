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

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class FormType extends BaseType
{
    /**
     * @var PropertyAccessorInterface
     */
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
            ->setAction($options['action'])
        ;

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
        $readOnly = $options['read_only'];

        if ($view->parent) {
            if ('' === $name) {
                throw new LogicException('Form node with empty name can be used only as root form node.');
            }

            // Complex fields are read-only if they themselves or their parents are.
            if (!$readOnly) {
                $readOnly = $view->parent->vars['read_only'];
            }
        }

        $view->vars = array_replace($view->vars, array(
            'read_only'  => $readOnly,
            'errors'     => $form->getErrors(),
            'valid'      => $form->isSubmitted() ? $form->isValid() : true,
            'value'      => $form->getViewData(),
            'data'       => $form->getNormData(),
            'required'   => $form->isRequired(),
            'max_length' => $options['max_length'],
            'pattern'    => $options['pattern'],
            'size'       => null,
            'label_attr' => $options['label_attr'],
            'compound'   => $form->getConfig()->getCompound(),
            'method'     => $form->getConfig()->getMethod(),
            'action'     => $form->getConfig()->getAction(),
            'submitted'  => $form->isSubmitted(),
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
        parent::setDefaultOptions($resolver);

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

        // BC with old "virtual" option
        $inheritData = function (Options $options) {
            if (null !== $options['virtual']) {
                // Uncomment this as soon as the deprecation note should be shown
                // trigger_error('The form option "virtual" is deprecated since version 2.3 and will be removed in 3.0. Use "inherit_data" instead.', E_USER_DEPRECATED);
                return $options['virtual'];
            }

            return false;
        };

        // If data is given, the form is locked to that data
        // (independent of its value)
        $resolver->setOptional(array(
            'data',
        ));

        $resolver->setDefaults(array(
            'data_class'         => $dataClass,
            'empty_data'         => $emptyData,
            'trim'               => true,
            'required'           => true,
            'read_only'          => false,
            'max_length'         => null,
            'pattern'            => null,
            'property_path'      => null,
            'mapped'             => true,
            'by_reference'       => true,
            'error_bubbling'     => $errorBubbling,
            'label_attr'         => array(),
            'virtual'            => null,
            'inherit_data'       => $inheritData,
            'compound'           => true,
            'method'             => 'POST',
            // According to RFC 2396 (http://www.ietf.org/rfc/rfc2396.txt)
            // section 4.2., empty URIs are considered same-document references
            'action'             => '',
        ));

        $resolver->setAllowedTypes(array(
            'label_attr' => 'array',
        ));
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
        return 'form';
    }
}
