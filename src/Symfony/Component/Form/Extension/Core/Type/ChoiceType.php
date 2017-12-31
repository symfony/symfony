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
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataMapper\RadioListMapper;
use Symfony\Component\Form\Extension\Core\DataMapper\CheckboxListMapper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\EventListener\MergeCollectionListener;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;
use Symfony\Component\Form\Util\FormUtil;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChoiceType extends AbstractType
{
    private $choiceListFactory;

    public function __construct(ChoiceListFactoryInterface $choiceListFactory = null)
    {
        $this->choiceListFactory = $choiceListFactory ?: new CachingFactoryDecorator(
            new PropertyAccessDecorator(
                new DefaultChoiceListFactory()
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choiceList = $this->createChoiceList($options);
        $builder->setAttribute('choice_list', $choiceList);

        if ($options['expanded']) {
            $builder->setDataMapper($options['multiple'] ? new CheckboxListMapper() : new RadioListMapper());

            // Initialize all choices before doing the index check below.
            // This helps in cases where index checks are optimized for non
            // initialized choice lists. For example, when using an SQL driver,
            // the index check would read in one SQL query and the initialization
            // requires another SQL query. When the initialization is done first,
            // one SQL query is sufficient.

            $choiceListView = $this->createChoiceListView($choiceList, $options);
            $builder->setAttribute('choice_list_view', $choiceListView);

            // Check if the choices already contain the empty value
            // Only add the placeholder option if this is not the case
            if (null !== $options['placeholder'] && 0 === count($choiceList->getChoicesForValues(array('')))) {
                $placeholderView = new ChoiceView(null, '', $options['placeholder']);

                // "placeholder" is a reserved name
                $this->addSubForm($builder, 'placeholder', $placeholderView, $options);
            }

            $this->addSubForms($builder, $choiceListView->preferredChoices, $options);
            $this->addSubForms($builder, $choiceListView->choices, $options);

            // Make sure that scalar, submitted values are converted to arrays
            // which can be submitted to the checkboxes/radio buttons
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $form = $event->getForm();
                $data = $event->getData();

                if (null === $data) {
                    $emptyData = $form->getConfig()->getEmptyData();

                    if (false === FormUtil::isEmpty($emptyData) && array() !== $emptyData) {
                        $data = is_callable($emptyData) ? call_user_func($emptyData, $form, $data) : $emptyData;
                    }
                }

                // Convert the submitted data to a string, if scalar, before
                // casting it to an array
                if (!is_array($data)) {
                    $data = (array) (string) $data;
                }

                // A map from submitted values to integers
                $valueMap = array_flip($data);

                // Make a copy of the value map to determine whether any unknown
                // values were submitted
                $unknownValues = $valueMap;

                // Reconstruct the data as mapping from child names to values
                $data = array();

                /** @var FormInterface $child */
                foreach ($form as $child) {
                    $value = $child->getConfig()->getOption('value');

                    // Add the value to $data with the child's name as key
                    if (isset($valueMap[$value])) {
                        $data[$child->getName()] = $value;
                        unset($unknownValues[$value]);
                        continue;
                    }
                }

                // The empty value is always known, independent of whether a
                // field exists for it or not
                unset($unknownValues['']);

                // Throw exception if unknown values were submitted
                if (count($unknownValues) > 0) {
                    throw new TransformationFailedException(sprintf(
                        'The choices "%s" do not exist in the choice list.',
                        implode('", "', array_keys($unknownValues))
                    ));
                }

                $event->setData($data);
            });
        }

        if ($options['multiple']) {
            // <select> tag with "multiple" option or list of checkbox inputs
            $builder->addViewTransformer(new ChoicesToValuesTransformer($choiceList));
        } else {
            // <select> tag without "multiple" option or list of radio inputs
            $builder->addViewTransformer(new ChoiceToValueTransformer($choiceList));
        }

        if ($options['multiple'] && $options['by_reference']) {
            // Make sure the collection created during the client->norm
            // transformation is merged back into the original collection
            $builder->addEventSubscriber(new MergeCollectionListener(true, true));
        }

        // To avoid issues when the submitted choices are arrays (i.e. array to string conversions),
        // we have to ensure that all elements of the submitted choice data are NULL, strings or ints.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();

            if (!is_array($data)) {
                return;
            }

            foreach ($data as $v) {
                if (null !== $v && !is_string($v) && !is_int($v)) {
                    throw new TransformationFailedException('All choices submitted must be NULL, strings or ints.');
                }
            }
        }, 256);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $choiceTranslationDomain = $options['choice_translation_domain'];
        if ($view->parent && null === $choiceTranslationDomain) {
            $choiceTranslationDomain = $view->vars['translation_domain'];
        }

        /** @var ChoiceListInterface $choiceList */
        $choiceList = $form->getConfig()->getAttribute('choice_list');

        /** @var ChoiceListView $choiceListView */
        $choiceListView = $form->getConfig()->hasAttribute('choice_list_view')
            ? $form->getConfig()->getAttribute('choice_list_view')
            : $this->createChoiceListView($choiceList, $options);

        $view->vars = array_replace($view->vars, array(
            'multiple' => $options['multiple'],
            'expanded' => $options['expanded'],
            'preferred_choices' => $choiceListView->preferredChoices,
            'choices' => $choiceListView->choices,
            'separator' => '-------------------',
            'placeholder' => null,
            'choice_translation_domain' => $choiceTranslationDomain,
        ));

        // The decision, whether a choice is selected, is potentially done
        // thousand of times during the rendering of a template. Provide a
        // closure here that is optimized for the value of the form, to
        // avoid making the type check inside the closure.
        if ($options['multiple']) {
            $view->vars['is_selected'] = function ($choice, array $values) {
                return in_array($choice, $values, true);
            };
        } else {
            $view->vars['is_selected'] = function ($choice, $value) {
                return $choice === $value;
            };
        }

        // Check if the choices already contain the empty value
        $view->vars['placeholder_in_choices'] = $choiceListView->hasPlaceholder();

        // Only add the empty value option if this is not the case
        if (null !== $options['placeholder'] && !$view->vars['placeholder_in_choices']) {
            $view->vars['placeholder'] = $options['placeholder'];
        }

        if ($options['multiple'] && !$options['expanded']) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->vars['full_name'] .= '[]';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['expanded']) {
            // Radio buttons should have the same name as the parent
            $childName = $view->vars['full_name'];

            // Checkboxes should append "[]" to allow multiple selection
            if ($options['multiple']) {
                $childName .= '[]';
            }

            foreach ($view as $childView) {
                $childView->vars['full_name'] = $childName;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $emptyData = function (Options $options) {
            if ($options['expanded'] && !$options['multiple']) {
                return;
            }

            if ($options['multiple']) {
                return array();
            }

            return '';
        };

        $placeholderDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $choicesAsValuesNormalizer = function (Options $options, $choicesAsValues) {
            // Not set by the user
            if (null === $choicesAsValues) {
                return true;
            }

            // Set by the user
            if (true !== $choicesAsValues) {
                throw new \RuntimeException(sprintf('The "choices_as_values" option of the %s should not be used. Remove it and flip the contents of the "choices" option instead.', get_class($this)));
            }

            @trigger_error('The "choices_as_values" option is deprecated since Symfony 3.1 and will be removed in 4.0. You should not use it anymore.', E_USER_DEPRECATED);

            return true;
        };

        $placeholderNormalizer = function (Options $options, $placeholder) {
            if ($options['multiple']) {
                // never use an empty value for this case
                return;
            } elseif ($options['required'] && ($options['expanded'] || isset($options['attr']['size']) && $options['attr']['size'] > 1)) {
                // placeholder for required radio buttons or a select with size > 1 does not make sense
                return;
            } elseif (false === $placeholder) {
                // an empty value should be added but the user decided otherwise
                return;
            } elseif ($options['expanded'] && '' === $placeholder) {
                // never use an empty label for radio buttons
                return 'None';
            }

            // empty value has been set explicitly
            return $placeholder;
        };

        $compound = function (Options $options) {
            return $options['expanded'];
        };

        $choiceTranslationDomainNormalizer = function (Options $options, $choiceTranslationDomain) {
            if (true === $choiceTranslationDomain) {
                return $options['translation_domain'];
            }

            return $choiceTranslationDomain;
        };

        $resolver->setDefaults(array(
            'multiple' => false,
            'expanded' => false,
            'choices' => array(),
            'choices_as_values' => null, // deprecated since 3.1
            'choice_loader' => null,
            'choice_label' => null,
            'choice_name' => null,
            'choice_value' => null,
            'choice_attr' => null,
            'preferred_choices' => array(),
            'group_by' => null,
            'empty_data' => $emptyData,
            'placeholder' => $placeholderDefault,
            'error_bubbling' => false,
            'compound' => $compound,
            // The view data is always a string, even if the "data" option
            // is manually set to an object.
            // See https://github.com/symfony/symfony/pull/5582
            'data_class' => null,
            'choice_translation_domain' => true,
        ));

        $resolver->setNormalizer('placeholder', $placeholderNormalizer);
        $resolver->setNormalizer('choice_translation_domain', $choiceTranslationDomainNormalizer);
        $resolver->setNormalizer('choices_as_values', $choicesAsValuesNormalizer);

        $resolver->setAllowedTypes('choices', array('null', 'array', '\Traversable'));
        $resolver->setAllowedTypes('choice_translation_domain', array('null', 'bool', 'string'));
        $resolver->setAllowedTypes('choice_loader', array('null', 'Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface'));
        $resolver->setAllowedTypes('choice_label', array('null', 'bool', 'callable', 'string', 'Symfony\Component\PropertyAccess\PropertyPath'));
        $resolver->setAllowedTypes('choice_name', array('null', 'callable', 'string', 'Symfony\Component\PropertyAccess\PropertyPath'));
        $resolver->setAllowedTypes('choice_value', array('null', 'callable', 'string', 'Symfony\Component\PropertyAccess\PropertyPath'));
        $resolver->setAllowedTypes('choice_attr', array('null', 'array', 'callable', 'string', 'Symfony\Component\PropertyAccess\PropertyPath'));
        $resolver->setAllowedTypes('preferred_choices', array('array', '\Traversable', 'callable', 'string', 'Symfony\Component\PropertyAccess\PropertyPath'));
        $resolver->setAllowedTypes('group_by', array('null', 'callable', 'string', 'Symfony\Component\PropertyAccess\PropertyPath'));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'choice';
    }

    /**
     * Adds the sub fields for an expanded choice field.
     */
    private function addSubForms(FormBuilderInterface $builder, array $choiceViews, array $options)
    {
        foreach ($choiceViews as $name => $choiceView) {
            // Flatten groups
            if (is_array($choiceView)) {
                $this->addSubForms($builder, $choiceView, $options);
                continue;
            }

            if ($choiceView instanceof ChoiceGroupView) {
                $this->addSubForms($builder, $choiceView->choices, $options);
                continue;
            }

            $this->addSubForm($builder, $name, $choiceView, $options);
        }
    }

    /**
     * @return mixed
     */
    private function addSubForm(FormBuilderInterface $builder, $name, ChoiceView $choiceView, array $options)
    {
        $choiceOpts = array(
            'value' => $choiceView->value,
            'label' => $choiceView->label,
            'attr' => $choiceView->attr,
            'translation_domain' => $options['translation_domain'],
            'block_name' => 'entry',
        );

        if ($options['multiple']) {
            $choiceType = __NAMESPACE__.'\CheckboxType';
            // The user can check 0 or more checkboxes. If required
            // is true, he is required to check all of them.
            $choiceOpts['required'] = false;
        } else {
            $choiceType = __NAMESPACE__.'\RadioType';
        }

        $builder->add($name, $choiceType, $choiceOpts);
    }

    private function createChoiceList(array $options)
    {
        if (null !== $options['choice_loader']) {
            return $this->choiceListFactory->createListFromLoader(
                $options['choice_loader'],
                $options['choice_value']
            );
        }

        // Harden against NULL values (like in EntityType and ModelType)
        $choices = null !== $options['choices'] ? $options['choices'] : array();

        return $this->choiceListFactory->createListFromChoices($choices, $options['choice_value']);
    }

    private function createChoiceListView(ChoiceListInterface $choiceList, array $options)
    {
        return $this->choiceListFactory->createView(
            $choiceList,
            $options['preferred_choices'],
            $options['choice_label'],
            $options['choice_name'],
            $options['group_by'],
            $options['choice_attr']
        );
    }
}
