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
use Symfony\Component\Form\Extension\Core\DataAccessor\CallbackAccessor;
use Symfony\Component\Form\Extension\Core\DataAccessor\ChainAccessor;
use Symfony\Component\Form\Extension\Core\DataAccessor\PropertyPathAccessor;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class FormType extends BaseType
{
    private $dataMapper;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->dataMapper = new DataMapper(new ChainAccessor([
            new CallbackAccessor(),
            new PropertyPathAccessor($propertyAccessor ?? PropertyAccess::createPropertyAccessor()),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $isDataOptionSet = \array_key_exists('data', $options);

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
            ->setDataMapper($options['compound'] ? $this->dataMapper : null)
            ->setMethod($options['method'])
            ->setAction($options['action']);

        if ($options['trim']) {
            $builder->addEventSubscriber(new TrimListener());
        }

        if (!method_exists($builder, 'setIsEmptyCallback')) {
            trigger_deprecation('symfony/form', '5.1', 'Not implementing the "%s::setIsEmptyCallback()" method in "%s" is deprecated.', FormConfigBuilderInterface::class, get_debug_type($builder));

            return;
        }

        $builder->setIsEmptyCallback($options['is_empty_callback']);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $name = $form->getName();
        $helpTranslationParameters = $options['help_translation_parameters'];

        if ($view->parent) {
            if ('' === $name) {
                throw new LogicException('Form node with empty name can be used only as root form node.');
            }

            // Complex fields are read-only if they themselves or their parents are.
            if (!isset($view->vars['attr']['readonly']) && isset($view->parent->vars['attr']['readonly']) && false !== $view->parent->vars['attr']['readonly']) {
                $view->vars['attr']['readonly'] = true;
            }

            $helpTranslationParameters = array_merge($view->parent->vars['help_translation_parameters'], $helpTranslationParameters);

            $rootFormAttrOption = $form->getRoot()->getConfig()->getOption('form_attr');
            if ($options['form_attr'] || $rootFormAttrOption) {
                $view->vars['attr']['form'] = \is_string($rootFormAttrOption) ? $rootFormAttrOption : $form->getRoot()->getName();
                if (empty($view->vars['attr']['form'])) {
                    throw new LogicException('"form_attr" option must be a string identifier on root form when it has no id.');
                }
            }
        } elseif (\is_string($options['form_attr'])) {
            $view->vars['id'] = $options['form_attr'];
        }

        $formConfig = $form->getConfig();
        $view->vars = array_replace($view->vars, [
            'errors' => $form->getErrors(),
            'valid' => $form->isSubmitted() ? $form->isValid() : true,
            'value' => $form->getViewData(),
            'data' => $form->getNormData(),
            'required' => $form->isRequired(),
            'size' => null,
            'label_attr' => $options['label_attr'],
            'help' => $options['help'],
            'help_attr' => $options['help_attr'],
            'help_html' => $options['help_html'],
            'help_translation_parameters' => $helpTranslationParameters,
            'compound' => $formConfig->getCompound(),
            'method' => $formConfig->getMethod(),
            'action' => $formConfig->getAction(),
            'submitted' => $form->isSubmitted(),
        ]);
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
                return $form->getConfig()->getCompound() ? [] : '';
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
            return $options['compound'] && !$options['inherit_data'];
        };

        // If data is given, the form is locked to that data
        // (independent of its value)
        $resolver->setDefined([
            'data',
        ]);

        $resolver->setDefaults([
            'data_class' => $dataClass,
            'empty_data' => $emptyData,
            'trim' => true,
            'required' => true,
            'property_path' => null,
            'mapped' => true,
            'by_reference' => true,
            'error_bubbling' => $errorBubbling,
            'label_attr' => [],
            'inherit_data' => false,
            'compound' => true,
            'method' => 'POST',
            // According to RFC 2396 (http://www.ietf.org/rfc/rfc2396.txt)
            // section 4.2., empty URIs are considered same-document references
            'action' => '',
            'attr' => [],
            'post_max_size_message' => 'The uploaded file was too large. Please try to upload a smaller file.',
            'upload_max_size_message' => $uploadMaxSizeMessage, // internal
            'allow_file_upload' => false,
            'help' => null,
            'help_attr' => [],
            'help_html' => false,
            'help_translation_parameters' => [],
            'invalid_message' => 'This value is not valid.',
            'invalid_message_parameters' => [],
            'is_empty_callback' => null,
            'getter' => null,
            'setter' => null,
            'form_attr' => false,
        ]);

        $resolver->setAllowedTypes('label_attr', 'array');
        $resolver->setAllowedTypes('action', 'string');
        $resolver->setAllowedTypes('upload_max_size_message', ['callable']);
        $resolver->setAllowedTypes('help', ['string', 'null']);
        $resolver->setAllowedTypes('help_attr', 'array');
        $resolver->setAllowedTypes('help_html', 'bool');
        $resolver->setAllowedTypes('is_empty_callback', ['null', 'callable']);
        $resolver->setAllowedTypes('getter', ['null', 'callable']);
        $resolver->setAllowedTypes('setter', ['null', 'callable']);
        $resolver->setAllowedTypes('form_attr', ['bool', 'string']);

        $resolver->setInfo('getter', 'A callable that accepts two arguments (the view data and the current form field) and must return a value.');
        $resolver->setInfo('setter', 'A callable that accepts three arguments (a reference to the view data, the submitted value and the current form field).');
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
    public function getBlockPrefix()
    {
        return 'form';
    }
}
