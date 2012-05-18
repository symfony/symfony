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
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class FormType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
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
            ->setData($options['data'])
            ->setDataMapper(new PropertyPathMapper())
        ;

        if ($options['trim']) {
            $builder->addEventSubscriber(new TrimListener());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormViewInterface $view, FormInterface $form, array $options)
    {
        $name = $form->getName();
        $readOnly = $options['read_only'];
        $translationDomain = $options['translation_domain'];

        if ($view->hasParent()) {
            if ('' === $name) {
                throw new FormException('Form node with empty name can be used only as root form node.');
            }

            if ('' !== ($parentFullName = $view->getParent()->getVar('full_name'))) {
                $id = sprintf('%s_%s', $view->getParent()->getVar('id'), $name);
                $fullName = sprintf('%s[%s]', $parentFullName, $name);
            } else {
                $id = $name;
                $fullName = $name;
            }

            // Complex fields are read-only if themselves or their parent is.
            $readOnly = $readOnly || $view->getParent()->getVar('read_only');

            if (!$translationDomain) {
                $translationDomain = $view->getParent()->getVar('translation_domain');
            }
        } else {
            $id = $name;
            $fullName = $name;

            // Strip leading underscores and digits. These are allowed in
            // form names, but not in HTML4 ID attributes.
            // http://www.w3.org/TR/html401/struct/global.html#adef-id
            $id = ltrim($id, '_0123456789');
        }

        $types = array();
        foreach ($form->getConfig()->getTypes() as $type) {
            $types[] = $type->getName();
        }

        if (!$translationDomain) {
            $translationDomain = 'messages';
        }

        $view->addVars(array(
            'form'               => $view,
            'id'                 => $id,
            'name'               => $name,
            'full_name'          => $fullName,
            'read_only'          => $readOnly,
            'errors'             => $form->getErrors(),
            'valid'              => $form->isBound() ? $form->isValid() : true,
            'value'              => $form->getViewData(),
            'disabled'           => $form->isDisabled(),
            'required'           => $form->isRequired(),
            'max_length'         => $options['max_length'],
            'pattern'            => $options['pattern'],
            'size'               => null,
            'label'              => $options['label'] ?: $this->humanize($form->getName()),
            'multipart'          => false,
            'attr'               => $options['attr'],
            'label_attr'         => $options['label_attr'],
            'compound'           => $options['compound'],
            'types'              => $types,
            'translation_domain' => $translationDomain,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormViewInterface $view, FormInterface $form, array $options)
    {
        $multipart = false;

        foreach ($view as $child) {
            if ($child->getVar('multipart')) {
                $multipart = true;
                break;
            }
        }

        $view->setVar('multipart', $multipart);
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        // Derive "data_class" option from passed "data" object
        $dataClass = function (Options $options) {
            return is_object($options['data']) ? get_class($options['data']) : null;
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
                return count($form) > 0 ? array() : '';
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

        $resolver->setDefaults(array(
            'data'               => null,
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
    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return new FormBuilder($name, $options['data_class'], new EventDispatcher(), $factory, $options);
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

    private function humanize($text)
    {
        return ucfirst(trim(strtolower(preg_replace('/[_\s]+/', ' ', $text))));
    }
}
