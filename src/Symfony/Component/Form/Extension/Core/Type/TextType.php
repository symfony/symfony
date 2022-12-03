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
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextType extends AbstractType implements DataTransformerInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // When empty_data is explicitly set to an empty string,
        // a string should always be returned when NULL is submitted
        // This gives more control and thus helps preventing some issues
        // with PHP 7 which allows type hinting strings in functions
        // See https://github.com/symfony/symfony/issues/5906#issuecomment-203189375
        if ('' === $options['empty_data']) {
            $builder->addViewTransformer($this);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'text';
    }

    public function transform(mixed $data): mixed
    {
        // Model data should not be transformed
        return $data;
    }

    public function reverseTransform(mixed $data): mixed
    {
        return $data ?? '';
    }
}
