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

            if (!$labelFormat) {
                $labelFormat = $view->parent->vars['label_format'];
            }
        } else {
            $id = $name;
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
        $blockPrefixes[] = $uniqueBlockPrefix;

        $view->vars = array_replace($view->vars, [
            'form' => $view,
            'id' => $id,
            'name' => $name,
            'full_name' => $fullName,
            'disabled' => $form->isDisabled(),
            'label' => $options['label'],
            'label_format' => $labelFormat,
            'multipart' => false,
            'attr' => $options['attr'],
            'block_prefixes' => $blockPrefixes,
            'unique_block_prefix' => $uniqueBlockPrefix,
            'translation_domain' => $translationDomain,
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
            'disabled' => false,
            'label' => null,
            'label_format' => null,
            'attr' => [],
            'translation_domain' => null,
            'auto_initialize' => true,
        ]);

        $resolver->setAllowedTypes('attr', 'array');
    }
}
