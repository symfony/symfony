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
use Symfony\Component\Form\EntryTypeProviderInterface;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A collection whose entries do not all share the same type.
 *
 * The type of each entry is decided by the "entry_type_provider" from the entry's data,
 * and "entry_options", "prototype_options" and "prototype_data" are keyed by the names
 * declared in "entry_types". Use CollectionType when every entry has the same type.
 */
class PolymorphicCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $resizePrototypeOptions = null;
        if ($options['allow_add'] && $options['prototype']) {
            $prototypes = $resizePrototypeOptions = [];
            foreach ($options['entry_types'] as $name => $entryType) {
                $resizePrototypeOptions[$name] = array_replace($options['entry_options'][$name], $options['prototype_options'][$name]);
                $prototypeOptions = array_replace([
                    'required' => $options['required'],
                    'label' => $options['prototype_name'].'label__',
                ], $resizePrototypeOptions[$name]);

                if (null !== $options['prototype_data'][$name]) {
                    $prototypeOptions['data'] = $options['prototype_data'][$name];
                }

                $prototypes[$name] = $builder->create($options['prototype_name'], $entryType, $prototypeOptions)->getForm();
            }

            $builder->setAttribute('prototypes', $prototypes);
        }

        $builder->addEventSubscriber(new ResizeFormListener(
            $options['entry_types'],
            $options['entry_options'],
            $options['allow_add'],
            $options['allow_delete'],
            $options['delete_empty'],
            $resizePrototypeOptions,
            $options['keep_as_list'],
            $options['entry_type_provider'],
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, [
            'allow_add' => $options['allow_add'],
            'allow_delete' => $options['allow_delete'],
        ]);

        if ($form->getConfig()->hasAttribute('prototypes')) {
            $view->vars['prototypes'] = array_map(
                static fn (FormInterface $prototype) => $prototype->setParent($form)->createView($view),
                $form->getConfig()->getAttribute('prototypes'),
            );
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // entries do not share a type, so the offset is computed for each of them
        foreach ($view as $name => $entryView) {
            array_splice($entryView->vars['block_prefixes'], self::prefixOffset($form[$name]), 0, 'collection_entry');
        }

        if (!$prototypes = $form->getConfig()->getAttribute('prototypes')) {
            return;
        }

        foreach ($prototypes as $name => $prototype) {
            if ($view->vars['prototypes'][$name]->vars['multipart']) {
                $view->vars['multipart'] = true;
            }

            array_splice($view->vars['prototypes'][$name]->vars['block_prefixes'], self::prefixOffset($prototype), 0, 'collection_entry');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_add' => false,
            'allow_delete' => false,
            'prototype' => true,
            'prototype_name' => '__name__',
            'entry_options' => [],
            'prototype_options' => [],
            'prototype_data' => [],
            'delete_empty' => false,
            'invalid_message' => 'The collection is invalid.',
            'keep_as_list' => false,
        ]);

        $resolver->setRequired(['entry_types', 'entry_type_provider']);

        $resolver->setNormalizer('entry_options', static function (Options $options, array $value) {
            $value = self::keyByEntryType($options, $value, 'entry_options', []);

            foreach ($value as $name => $entryOptions) {
                $value[$name]['block_name'] = 'entry';
            }

            return $value;
        });
        $resolver->setNormalizer('prototype_options', static fn (Options $options, array $value) => self::keyByEntryType($options, $value, 'prototype_options', []));
        $resolver->setNormalizer('prototype_data', static fn (Options $options, array $value) => self::keyByEntryType($options, $value, 'prototype_data', null));

        $resolver->setAllowedTypes('entry_types', 'string[]');
        $resolver->setAllowedTypes('entry_type_provider', EntryTypeProviderInterface::class);
        $resolver->setAllowedTypes('entry_options', 'array');
        $resolver->setAllowedTypes('prototype_options', 'array');
        $resolver->setAllowedTypes('prototype_data', 'array');
        $resolver->setAllowedTypes('delete_empty', ['bool', 'callable']);
        $resolver->setAllowedTypes('keep_as_list', ['bool']);
    }

    public function getBlockPrefix(): string
    {
        return 'polymorphic_collection';
    }

    private static function keyByEntryType(Options $options, array $value, string $option, mixed $default): array
    {
        if ($unknown = array_diff_key($value, $options['entry_types'])) {
            throw new InvalidOptionsException(\sprintf('The "%s" option can only use keys of the "entry_types" option. Allowed keys are "%s", but got "%s".', $option, implode('", "', array_keys($options['entry_types'])), implode('", "', array_keys($unknown))));
        }

        foreach ($options['entry_types'] as $name => $entryType) {
            $value[$name] ??= $default;
        }

        return $value;
    }

    private static function prefixOffset(FormInterface $form): int
    {
        // the entry type may define a block prefix of its own, which sits before the type's own one
        return $form->getConfig()->getOption('block_prefix') ? -3 : -2;
    }
}
