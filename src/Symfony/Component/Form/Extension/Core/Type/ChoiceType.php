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
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceAttr;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceFieldName;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceFilter;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceLabel;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceLoader;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceTranslationParameters;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceValue;
use Symfony\Component\Form\ChoiceList\Factory\Cache\GroupBy;
use Symfony\Component\Form\ChoiceList\Factory\Cache\PreferredChoice;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataMapper\CheckboxListMapper;
use Symfony\Component\Form\Extension\Core\DataMapper\RadioListMapper;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer;
use Symfony\Component\Form\Extension\Core\EventListener\MergeCollectionListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChoiceType extends AbstractType
{
    private ChoiceListFactoryInterface $choiceListFactory;
    private ?TranslatorInterface $translator;

    public function __construct(ChoiceListFactoryInterface $choiceListFactory = null, TranslatorInterface $translator = null)
    {
        $this->choiceListFactory = $choiceListFactory ?? new CachingFactoryDecorator(
            new PropertyAccessDecorator(
                new DefaultChoiceListFactory()
            )
        );
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $unknownValues = [];
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
            if (null !== $options['placeholder'] && 0 === \count($choiceList->getChoicesForValues(['']))) {
                $placeholderView = new ChoiceView(null, '', $options['placeholder']);

                // "placeholder" is a reserved name
                $this->addSubForm($builder, 'placeholder', $placeholderView, $options);
            }

            $this->addSubForms($builder, $choiceListView->preferredChoices, $options);
            $this->addSubForms($builder, $choiceListView->choices, $options);
        }

        if ($options['expanded'] || $options['multiple']) {
            // Make sure that scalar, submitted values are converted to arrays
            // which can be submitted to the checkboxes/radio buttons
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($choiceList, $options, &$unknownValues) {
                $form = $event->getForm();
                $data = $event->getData();

                // Since the type always use mapper an empty array will not be
                // considered as empty in Form::submit(), we need to evaluate
                // empty data here so its value is submitted to sub forms
                if (null === $data) {
                    $emptyData = $form->getConfig()->getEmptyData();
                    $data = $emptyData instanceof \Closure ? $emptyData($form, $data) : $emptyData;
                }

                // Convert the submitted data to a string, if scalar, before
                // casting it to an array
                if (!\is_array($data)) {
                    if ($options['multiple']) {
                        throw new TransformationFailedException('Expected an array.');
                    }

                    $data = (array) (string) $data;
                }

                // A map from submitted values to integers
                $valueMap = array_flip($data);

                // Make a copy of the value map to determine whether any unknown
                // values were submitted
                $unknownValues = $valueMap;

                // Reconstruct the data as mapping from child names to values
                $knownValues = [];

                if ($options['expanded']) {
                    /** @var FormInterface $child */
                    foreach ($form as $child) {
                        $value = $child->getConfig()->getOption('value');

                        // Add the value to $data with the child's name as key
                        if (isset($valueMap[$value])) {
                            $knownValues[$child->getName()] = $value;
                            unset($unknownValues[$value]);
                            continue;
                        } else {
                            $knownValues[$child->getName()] = null;
                        }
                    }
                } else {
                    foreach ($choiceList->getChoicesForValues($data) as $key => $choice) {
                        $knownValues[] = $data[$key];
                        unset($unknownValues[$data[$key]]);
                    }
                }

                // The empty value is always known, independent of whether a
                // field exists for it or not
                unset($unknownValues['']);

                // Throw exception if unknown values were submitted (multiple choices will be handled in a different event listener below)
                if (\count($unknownValues) > 0 && !$options['multiple']) {
                    throw new TransformationFailedException(sprintf('The choices "%s" do not exist in the choice list.', implode('", "', array_keys($unknownValues))));
                }

                $event->setData($knownValues);
            });
        }

        if ($options['multiple']) {
            $messageTemplate = $options['invalid_message'] ?? 'The value {{ value }} is not valid.';

            $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use (&$unknownValues, $messageTemplate) {
                // Throw exception if unknown values were submitted
                if (\count($unknownValues) > 0) {
                    $form = $event->getForm();

                    $clientDataAsString = \is_scalar($form->getViewData()) ? (string) $form->getViewData() : (\is_array($form->getViewData()) ? implode('", "', array_keys($unknownValues)) : \gettype($form->getViewData()));

                    if (null !== $this->translator) {
                        $message = $this->translator->trans($messageTemplate, ['{{ value }}' => $clientDataAsString], 'validators');
                    } else {
                        $message = strtr($messageTemplate, ['{{ value }}' => $clientDataAsString]);
                    }

                    $form->addError(new FormError($message, $messageTemplate, ['{{ value }}' => $clientDataAsString], null, new TransformationFailedException(sprintf('The choices "%s" do not exist in the choice list.', $clientDataAsString))));
                }
            });

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

            if (!\is_array($data)) {
                return;
            }

            foreach ($data as $v) {
                if (null !== $v && !\is_string($v) && !\is_int($v)) {
                    throw new TransformationFailedException('All choices submitted must be NULL, strings or ints.');
                }
            }
        }, 256);
    }

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

        $view->vars = array_replace($view->vars, [
            'multiple' => $options['multiple'],
            'expanded' => $options['expanded'],
            'preferred_choices' => $choiceListView->preferredChoices,
            'choices' => $choiceListView->choices,
            'separator' => '-------------------',
            'placeholder' => null,
            'choice_translation_domain' => $choiceTranslationDomain,
            'choice_translation_parameters' => $options['choice_translation_parameters'],
        ]);

        // The decision, whether a choice is selected, is potentially done
        // thousand of times during the rendering of a template. Provide a
        // closure here that is optimized for the value of the form, to
        // avoid making the type check inside the closure.
        if ($options['multiple']) {
            $view->vars['is_selected'] = function ($choice, array $values) {
                return \in_array($choice, $values, true);
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $emptyData = function (Options $options) {
            if ($options['expanded'] && !$options['multiple']) {
                return null;
            }

            if ($options['multiple']) {
                return [];
            }

            return '';
        };

        $placeholderDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $placeholderNormalizer = function (Options $options, $placeholder) {
            if ($options['multiple']) {
                // never use an empty value for this case
                return null;
            } elseif ($options['required'] && ($options['expanded'] || isset($options['attr']['size']) && $options['attr']['size'] > 1)) {
                // placeholder for required radio buttons or a select with size > 1 does not make sense
                return null;
            } elseif (false === $placeholder) {
                // an empty value should be added but the user decided otherwise
                return null;
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

        $resolver->setDefaults([
            'multiple' => false,
            'expanded' => false,
            'choices' => [],
            'choice_filter' => null,
            'choice_loader' => null,
            'choice_label' => null,
            'choice_name' => null,
            'choice_value' => null,
            'choice_attr' => null,
            'choice_translation_parameters' => [],
            'preferred_choices' => [],
            'group_by' => null,
            'empty_data' => $emptyData,
            'placeholder' => $placeholderDefault,
            'error_bubbling' => false,
            'compound' => $compound,
            // The view data is always a string or an array of strings,
            // even if the "data" option is manually set to an object.
            // See https://github.com/symfony/symfony/pull/5582
            'data_class' => null,
            'choice_translation_domain' => true,
            'trim' => false,
            'invalid_message' => 'The selected choice is invalid.',
        ]);

        $resolver->setNormalizer('placeholder', $placeholderNormalizer);
        $resolver->setNormalizer('choice_translation_domain', $choiceTranslationDomainNormalizer);

        $resolver->setAllowedTypes('choices', ['null', 'array', \Traversable::class]);
        $resolver->setAllowedTypes('choice_translation_domain', ['null', 'bool', 'string']);
        $resolver->setAllowedTypes('choice_loader', ['null', ChoiceLoaderInterface::class, ChoiceLoader::class]);
        $resolver->setAllowedTypes('choice_filter', ['null', 'callable', 'string', PropertyPath::class, ChoiceFilter::class]);
        $resolver->setAllowedTypes('choice_label', ['null', 'bool', 'callable', 'string', PropertyPath::class, ChoiceLabel::class]);
        $resolver->setAllowedTypes('choice_name', ['null', 'callable', 'string', PropertyPath::class, ChoiceFieldName::class]);
        $resolver->setAllowedTypes('choice_value', ['null', 'callable', 'string', PropertyPath::class, ChoiceValue::class]);
        $resolver->setAllowedTypes('choice_attr', ['null', 'array', 'callable', 'string', PropertyPath::class, ChoiceAttr::class]);
        $resolver->setAllowedTypes('choice_translation_parameters', ['null', 'array', 'callable', ChoiceTranslationParameters::class]);
        $resolver->setAllowedTypes('preferred_choices', ['array', \Traversable::class, 'callable', 'string', PropertyPath::class, PreferredChoice::class]);
        $resolver->setAllowedTypes('group_by', ['null', 'callable', 'string', PropertyPath::class, GroupBy::class]);
    }

    public function getBlockPrefix(): string
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
            if (\is_array($choiceView)) {
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

    private function addSubForm(FormBuilderInterface $builder, string $name, ChoiceView $choiceView, array $options)
    {
        $choiceOpts = [
            'value' => $choiceView->value,
            'label' => $choiceView->label,
            'label_html' => $options['label_html'],
            'attr' => $choiceView->attr,
            'label_translation_parameters' => $choiceView->labelTranslationParameters,
            'translation_domain' => $options['choice_translation_domain'],
            'block_name' => 'entry',
        ];

        if ($options['multiple']) {
            $choiceType = CheckboxType::class;
            // The user can check 0 or more checkboxes. If required
            // is true, they are required to check all of them.
            $choiceOpts['required'] = false;
        } else {
            $choiceType = RadioType::class;
        }

        $builder->add($name, $choiceType, $choiceOpts);
    }

    private function createChoiceList(array $options)
    {
        if (null !== $options['choice_loader']) {
            return $this->choiceListFactory->createListFromLoader(
                $options['choice_loader'],
                $options['choice_value'],
                $options['choice_filter']
            );
        }

        // Harden against NULL values (like in EntityType and ModelType)
        $choices = null !== $options['choices'] ? $options['choices'] : [];

        return $this->choiceListFactory->createListFromChoices(
            $choices,
            $options['choice_value'],
            $options['choice_filter']
        );
    }

    private function createChoiceListView(ChoiceListInterface $choiceList, array $options)
    {
        return $this->choiceListFactory->createView(
            $choiceList,
            $options['preferred_choices'],
            $options['choice_label'],
            $options['choice_name'],
            $options['group_by'],
            $options['choice_attr'],
            $options['choice_translation_parameters']
        );
    }
}
