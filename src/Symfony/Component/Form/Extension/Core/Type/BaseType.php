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

use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Encapsulates common logic of {@link FormType} and {@link ButtonType}.
 *
 * This type does not appear in the form's type inheritance chain and as such
 * cannot be extended (via {@link \Symfony\Component\Form\FormExtensionInterface}) nor themed.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class BaseType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDisabled($options['disabled']);
        $builder->setAutoInitialize($options['auto_initialize']);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $name = $form->getName();
        $blockName = $options['block_name'] ?: $form->getName();
        $translationDomain = $options['translation_domain'];
        $labelTranslationParameters = $options['label_translation_parameters'];
        $attrTranslationParameters = $options['attr_translation_parameters'];
        $labelFormat = $options['label_format'];

        if ($view->parent) {
            if ('' !== ($parentFullName = $view->parent->vars['full_name'])) {
                $id = sprintf('%s_%s', $view->parent->vars['id'], $name);
                $fullName = sprintf('%s[%s]', $parentFullName, $name);
                $uniqueBlockPrefix = sprintf('%s_%s', $view->parent->vars['unique_block_prefix'], $blockName);
            } else {
                $id = $name;
                $fullName = $name;
                $uniqueBlockPrefix = '_'.$blockName;
            }

            if (null === $translationDomain) {
                $translationDomain = $view->parent->vars['translation_domain'];
            }

            $labelTranslationParameters = array_merge($view->parent->vars['label_translation_parameters'], $labelTranslationParameters);
            $attrTranslationParameters = array_merge($view->parent->vars['attr_translation_parameters'], $attrTranslationParameters);

            if (!$labelFormat) {
                $labelFormat = $view->parent->vars['label_format'];
            }

            $rootFormAttrOption = $form->getRoot()->getConfig()->getOption('form_attr');
            if ($options['form_attr'] || $rootFormAttrOption) {
                $options['attr']['form'] = \is_string($rootFormAttrOption) ? $rootFormAttrOption : $form->getRoot()->getName();
                if (empty($options['attr']['form'])) {
                    throw new LogicException('"form_attr" option must be a string identifier on root form when it has no id.');
                }
            }
        } else {
            $id = \is_string($options['form_attr']) ? $options['form_attr'] : $name;
            $fullName = $name;
            $uniqueBlockPrefix = '_'.$blockName;

            // Strip leading underscores and digits. These are allowed in
            // form names, but not in HTML4 ID attributes.
            // https://www.w3.org/TR/html401/struct/global#adef-id
            $id = ltrim($id, '_0123456789');
        }

        $blockPrefixes = [];
        for ($type = $form->getConfig()->getType(); null !== $type; $type = $type->getParent()) {
            array_unshift($blockPrefixes, $type->getBlockPrefix());
        }
        if (null !== $options['block_prefix']) {
            $blockPrefixes[] = $options['block_prefix'];
        }
        $blockPrefixes[] = $uniqueBlockPrefix;

        $view->vars = array_replace($view->vars, [
            'form' => $view,
            'id' => $id,
            'name' => $name,
            'full_name' => $fullName,
            'disabled' => $form->isDisabled(),
            'label' => $options['label'],
            'label_format' => $labelFormat,
            'label_html' => $options['label_html'],
            'multipart' => false,
            'attr' => $options['attr'],
            'block_prefixes' => $blockPrefixes,
            'unique_block_prefix' => $uniqueBlockPrefix,
            'row_attr' => $options['row_attr'],
            'translation_domain' => $translationDomain,
            'label_translation_parameters' => $labelTranslationParameters,
            'attr_translation_parameters' => $attrTranslationParameters,
            'priority' => $options['priority'],
            // Using the block name here speeds up performance in collection
            // forms, where each entry has the same full block name.
            // Including the type is important too, because if rows of a
            // collection form have different types (dynamically), they should
            // be rendered differently.
            // https://github.com/symfony/symfony/issues/5038
            AbstractRendererEngine::CACHE_KEY_VAR => $uniqueBlockPrefix.'_'.$form->getConfig()->getType()->getBlockPrefix(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'block_name' => null,
            'block_prefix' => null,
            'disabled' => false,
            'label' => null,
            'label_format' => null,
            'row_attr' => [],
            'label_html' => false,
            'label_translation_parameters' => [],
            'attr_translation_parameters' => [],
            'attr' => [],
            'translation_domain' => null,
            'auto_initialize' => true,
            'priority' => 0,
            'form_attr' => false,
        ]);

        $resolver->setAllowedTypes('block_prefix', ['null', 'string']);
        $resolver->setAllowedTypes('attr', 'array');
        $resolver->setAllowedTypes('row_attr', 'array');
        $resolver->setAllowedTypes('label_html', 'bool');
        $resolver->setAllowedTypes('priority', 'int');
        $resolver->setAllowedTypes('form_attr', ['bool', 'string']);

        $resolver->setInfo('priority', 'The form rendering priority (higher priorities will be rendered first)');
    }
}
