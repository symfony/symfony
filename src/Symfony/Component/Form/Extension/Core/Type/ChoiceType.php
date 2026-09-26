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
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceHelp;
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
use Symfony\Component\Form\ChoiceList\Loader\LazyChoiceLoader;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Exception\LogicException;
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
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class ChoiceType extends AbstractType
{
    private ChoiceListFactoryInterface $choiceListFactory;

    public function __construct(
        ?ChoiceListFactoryInterface $choiceListFactory = null,
        private ?TranslatorInterface $translator = null,
    ) {
        $this->choiceListFactory = $choiceListFactory ?? new CachingFactoryDecorator(
            new PropertyAccessDecorator(
                new DefaultChoiceListFactory()
            )
        );
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
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
            if (null !== $options['placeholder'] && !$choiceList->getChoicesForValues([''])) {
                $placeholderView = new ChoiceView(null, '', $options['placeholder'], $options['placeholder_attr']);

                // "placeholder" is a reserved name
                $this->addSubForm($builder, 'placeholder', $placeholderView, $options);
            }

            $this->addSubForms($builder, $choiceListView->preferredChoices, $options);
            $this->addSubForms($builder, $choiceListView->choices, $options);
        }

        if ($options['expanded'] || $options['multiple']) {
            // Make sure that scalar, submitted values are converted to arrays
            // which can be submitted to the checkboxes/radio buttons
            $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (PreSubmitEvent $event) use ($choiceList, $options, &$unknownValues) {
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
                // `null` entries are produced by MissingDataHandler for unchecked checkbox children
                $valueMap = array_flip(array_filter($data, static fn ($v) => null !== $v));

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
                        }

                        $knownValues[$child->getName()] = null;
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
                if ($unknownValues && !$options['multiple']) {
                    throw new TransformationFailedException(\sprintf('The choices "%s" do not exist in the choice list.', implode('", "', array_keys($unknownValues))));
                }

                $event->setData($knownValues);
            });
        }

        if ($options['multiple']) {
            $messageTemplate = $options['invalid_message'] ?? 'The value {{ value }} is not valid.';
            $translator = $this->translator;

            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use (&$unknownValues, $messageTemplate, $translator) {
                // Throw exception if unknown values were submitted
                if ($unknownValues) {
                    $form = $event->getForm();

                    $clientDataAsString = \is_scalar($form->getViewData()) ? (string) $form->getViewData() : (\is_array($form->getViewData()) ? implode('", "', array_keys($unknownValues)) : \gettype($form->getViewData()));

                    if ($translator) {
                        $message = $translator->trans($messageTemplate, ['{{ value }}' => $clientDataAsString], 'validators');
                    } else {
                        $message = strtr($messageTemplate, ['{{ value }}' => $clientDataAsString]);
                    }

                    $form->addError(new FormError($message, $messageTemplate, ['{{ value }}' => $clientDataAsString], null, new TransformationFailedException(\sprintf('The choices "%s" do not exist in the choice list.', $clientDataAsString))));
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
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) {
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

    public function buildView(FormView $view, FormInterface $form, array $options): void
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
            'separator' => $options['separator'],
            'separator_html' => $options['separator_html'],
            'placeholder' => null,
            'placeholder_attr' => [],
            'choice_translation_domain' => $choiceTranslationDomain,
            'choice_translation_parameters' => $options['choice_translation_parameters'],
        ]);

        // The decision, whether a choice is selected, is potentially done
        // thousand of times during the rendering of a template. Provide a
        // closure here that is optimized for the value of the form, to
        // avoid making the type check inside the closure.
        if ($options['multiple']) {
            $view->vars['is_selected'] = static fn ($choice, array $values) => \in_array($choice, $values, true);
        } else {
            $view->vars['is_selected'] = static fn ($choice, $value) => $choice === $value;
        }

        // Check if the choices already contain the empty value
        $view->vars['placeholder_in_choices'] = $choiceListView->hasPlaceholder();

        // Only add the empty value option if this is not the case
        if (null !== $options['placeholder'] && !$view->vars['placeholder_in_choices']) {
            $view->vars['placeholder'] = $options['placeholder'];
            $view->vars['placeholder_attr'] = $options['placeholder_attr'];
        }

        if (false !== $options['sort_choices']) {
            // Labels are sorted once translated, which is only possible here: the
            // translation domain may be inherited from the parent view
            $collator = new \Collator($this->translator?->getLocale() ?? \Locale::getDefault());
            // Numbers are compared by their value, so "2" comes before "10"
            $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);

            $compare = true === $options['sort_choices']
                ? static fn (ChoiceGroupView|ChoiceView $a, ChoiceGroupView|ChoiceView $b, string $labelA, string $labelB): int => $collator->compare($labelA, $labelB)
                : $options['sort_choices'](...);

            $view->vars['preferred_choices'] = $this->sortChoiceViews($view->vars['preferred_choices'], $compare, $collator, $choiceTranslationDomain);
            $view->vars['choices'] = $this->sortChoiceViews($view->vars['choices'], $compare, $collator, $choiceTranslationDomain);
        }

        if ($options['multiple'] && !$options['expanded']) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->vars['full_name'] .= '[]';
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['duplicate_preferred_choices'] = $options['duplicate_preferred_choices'];

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

            if (false !== $options['sort_choices']) {
                // Expanded choices are rendered from the children, which were
                // added in the unsorted order: they must follow the sorted views
                $children = $view->children;
                $sorted = isset($children['placeholder']) ? ['placeholder' => $children['placeholder']] : [];

                // Choice names are often integers, so both lists must not be merged
                foreach ([$view->vars['preferred_choices'], $view->vars['choices']] as $choiceViews) {
                    foreach ($this->getChoiceViewNames($choiceViews) as $name) {
                        if (isset($children[$name])) {
                            $sorted[$name] ??= $children[$name];
                        }
                    }
                }

                // Children not backed by a choice view keep their position at the end
                $view->children = $sorted + $children;
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $emptyData = static function (Options $options) {
            if ($options['expanded'] && !$options['multiple']) {
                return null;
            }

            if ($options['multiple']) {
                return [];
            }

            return '';
        };

        $placeholderDefault = static fn (Options $options) => $options['required'] ? null : '';

        $placeholderNormalizer = static function (Options $options, $placeholder) {
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

        $compound = static fn (Options $options) => $options['expanded'];

        $choiceTranslationDomainNormalizer = static function (Options $options, $choiceTranslationDomain) {
            if (true === $choiceTranslationDomain) {
                return $options['translation_domain'];
            }

            return $choiceTranslationDomain;
        };

        $choiceLoaderNormalizer = static function (Options $options, ?ChoiceLoaderInterface $choiceLoader) {
            if (!$options['choice_lazy']) {
                return $choiceLoader;
            }

            if (null === $choiceLoader) {
                throw new LogicException('The "choice_lazy" option can only be used if the "choice_loader" option is set.');
            }

            return new LazyChoiceLoader($choiceLoader);
        };

        $placeholderAttr = static fn (Options $options) => $options['required'] ? ['hidden' => true] : [];

        $resolver->setDefaults([
            'multiple' => false,
            'expanded' => false,
            'choices' => [],
            'choice_filter' => null,
            'choice_lazy' => false,
            'choice_loader' => null,
            'choice_label' => null,
            'choice_name' => null,
            'choice_value' => null,
            'choice_attr' => null,
            'choice_help' => null,
            'choice_translation_parameters' => [],
            'preferred_choices' => [],
            'separator' => '-------------------',
            'separator_html' => false,
            'duplicate_preferred_choices' => true,
            'sort_choices' => false,
            'group_by' => null,
            'empty_data' => $emptyData,
            'placeholder' => $placeholderDefault,
            'placeholder_attr' => $placeholderAttr,
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
        $resolver->setNormalizer('choice_loader', $choiceLoaderNormalizer);
        $resolver->setNormalizer('sort_choices', static function (Options $options, bool|callable $sortChoices) {
            if (false !== $sortChoices && !\extension_loaded('intl')) {
                throw new LogicException('The "sort_choices" option requires the "intl" PHP extension.');
            }

            return $sortChoices;
        });

        $resolver->setAllowedTypes('choices', ['null', 'array', \Traversable::class]);
        $resolver->setAllowedTypes('choice_translation_domain', ['null', 'bool', 'string']);
        $resolver->setAllowedTypes('choice_lazy', 'bool');
        $resolver->setAllowedTypes('choice_loader', ['null', ChoiceLoaderInterface::class, ChoiceLoader::class]);
        $resolver->setAllowedTypes('choice_filter', ['null', 'callable', 'string', PropertyPath::class, ChoiceFilter::class]);
        $resolver->setAllowedTypes('choice_label', ['null', 'bool', 'callable', 'string', PropertyPath::class, ChoiceLabel::class]);
        $resolver->setAllowedTypes('choice_name', ['null', 'callable', 'string', PropertyPath::class, ChoiceFieldName::class]);
        $resolver->setAllowedTypes('choice_value', ['null', 'callable', 'string', PropertyPath::class, ChoiceValue::class]);
        $resolver->setAllowedTypes('choice_attr', ['null', 'array', 'callable', 'string', PropertyPath::class, ChoiceAttr::class]);
        $resolver->setAllowedTypes('choice_help', ['null', 'array', 'callable', 'string', PropertyPath::class, ChoiceHelp::class]);
        $resolver->setAllowedTypes('choice_translation_parameters', ['null', 'array', 'callable', ChoiceTranslationParameters::class]);
        $resolver->setAllowedTypes('placeholder_attr', ['array']);
        $resolver->setAllowedTypes('preferred_choices', ['array', \Traversable::class, 'callable', 'string', PropertyPath::class, PreferredChoice::class]);
        $resolver->setAllowedTypes('separator', ['string']);
        $resolver->setAllowedTypes('separator_html', ['bool']);
        $resolver->setAllowedTypes('duplicate_preferred_choices', 'bool');
        $resolver->setAllowedTypes('sort_choices', ['bool', 'callable']);
        $resolver->setAllowedTypes('group_by', ['null', 'callable', 'string', PropertyPath::class, GroupBy::class]);

        $resolver->setInfo('choice_lazy', 'Load choices on demand. When set to true, only the selected choices are loaded and rendered.');
        $resolver->setInfo('sort_choices', 'Sort the choices by their translated label: true for the alphabetical order of the current locale, or a callable comparing two choices (their views, translated labels and the collator of the locale).');
    }

    public function getBlockPrefix(): string
    {
        return 'choice';
    }

    /**
     * Adds the sub fields for an expanded choice field.
     */
    private function addSubForms(FormBuilderInterface $builder, array $choiceViews, array $options): void
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

    private function addSubForm(FormBuilderInterface $builder, string $name, ChoiceView $choiceView, array $options): void
    {
        $choiceOpts = [
            'value' => $choiceView->value,
            'label' => $choiceView->label,
            'label_html' => $options['label_html'],
            'help' => $choiceView->help,
            'help_html' => $options['help_html'],
            'attr' => $choiceView->attr,
            'label_translation_parameters' => $choiceView->labelTranslationParameters,
            'translation_domain' => 'placeholder' === $name ? $options['translation_domain'] : $options['choice_translation_domain'],
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

    private function createChoiceList(array $options): ChoiceListInterface
    {
        if (null !== $options['choice_loader']) {
            return $this->choiceListFactory->createListFromLoader(
                $options['choice_loader'],
                $options['choice_value'],
                $options['choice_filter']
            );
        }

        // Harden against NULL values (like in EntityType and ModelType)
        $choices = $options['choices'] ?? [];

        return $this->choiceListFactory->createListFromChoices(
            $choices,
            $options['choice_value'],
            $options['choice_filter']
        );
    }

    /**
     * Sorts choice views by comparing them along with their translated label.
     *
     * @param array<array-key, ChoiceGroupView|ChoiceView>                                                    $choiceViews
     * @param \Closure(ChoiceGroupView|ChoiceView, ChoiceGroupView|ChoiceView, string, string, \Collator):int $compare
     *
     * @return array<array-key, ChoiceGroupView|ChoiceView>
     */
    private function sortChoiceViews(array $choiceViews, \Closure $compare, \Collator $collator, string|false|null $translationDomain): array
    {
        // The empty choice acts as placeholder and stays on top
        $sorted = [];
        foreach ($choiceViews as $key => $choiceView) {
            if ($choiceView instanceof ChoiceView && '' === $choiceView->value) {
                $sorted[$key] = $choiceView;
                unset($choiceViews[$key]);
            }
        }

        $labels = [];
        foreach ($choiceViews as $key => $choiceView) {
            if ($choiceView instanceof ChoiceGroupView) {
                // Cached views are shared between forms and must not be modified
                $choiceViews[$key] = new ChoiceGroupView($choiceView->label, $this->sortChoiceViews($choiceView->choices, $compare, $collator, $translationDomain));
                $labels[$key] = $this->translateLabel($choiceView->label, [], $translationDomain);
            } else {
                $labels[$key] = $this->translateLabel($choiceView->label, $choiceView->labelTranslationParameters, $translationDomain);
            }
        }

        // Sorting is stable, choices compared as equal keep their original order
        $keys = array_keys($choiceViews);
        usort($keys, static fn (int|string $a, int|string $b): int => $compare($choiceViews[$a], $choiceViews[$b], $labels[$a], $labels[$b], $collator));

        foreach ($keys as $key) {
            $sorted[$key] = $choiceViews[$key];
        }

        return $sorted;
    }

    /**
     * @param array<array-key, ChoiceGroupView|ChoiceView> $choiceViews
     *
     * @return iterable<array-key>
     */
    private function getChoiceViewNames(array $choiceViews): iterable
    {
        foreach ($choiceViews as $name => $choiceView) {
            if ($choiceView instanceof ChoiceGroupView) {
                yield from $this->getChoiceViewNames($choiceView->choices);
            } else {
                yield $name;
            }
        }
    }

    private function translateLabel(string|TranslatableInterface|false $label, array $parameters, string|false|null $translationDomain): string
    {
        if (false === $label) {
            return '';
        }

        if ($label instanceof TranslatableInterface) {
            return $label->trans($this->translator ?? new class implements TranslatorInterface {
                use TranslatorTrait;
            });
        }

        if (!$this->translator || false === $translationDomain) {
            return $label;
        }

        return $this->translator->trans($label, $parameters, $translationDomain);
    }

    private function createChoiceListView(ChoiceListInterface $choiceList, array $options): ChoiceListView
    {
        return $this->choiceListFactory->createView(
            $choiceList,
            $options['preferred_choices'],
            $options['choice_label'],
            $options['choice_name'],
            $options['group_by'],
            $options['choice_attr'],
            $options['choice_translation_parameters'],
            $options['duplicate_preferred_choices'],
            $options['choice_help'],
        );
    }
}
