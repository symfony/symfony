<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A form type derived at compile time from a class marked with the AsFormType attribute.
 *
 * @author Benjamin Georgeault <git@wedgesama.fr>
 *
 * @internal
 */
final class DataClassType extends AbstractType
{
    /**
     * @param class-string                                                  $dataClass
     * @param class-string                                                  $parent
     * @param array<string, array{class-string|null, array<string, mixed>}> $fields
     * @param array<string, mixed>                                          $options
     */
    public function __construct(
        private string $dataClass,
        private string $parent,
        private string $blockPrefix,
        private array $fields,
        private array $options,
    ) {
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults($this->options + ['data_class' => $this->dataClass]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($this->fields as $name => [$type, $fieldOptions]) {
            $builder->add($name, $type, $fieldOptions);
        }
    }

    public function getBlockPrefix(): string
    {
        return $this->blockPrefix;
    }

    /**
     * @return class-string
     */
    public function getDataClass(): string
    {
        return $this->dataClass;
    }
}
