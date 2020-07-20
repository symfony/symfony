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

use Closure;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\DataMapper\AccessorMapper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccessorMapperExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['get'] && !$options['set']) {
            return;
        }

        if (!$dataMapper = $builder->getDataMapper()) {
            return;
        }

        $builder->setDataMapper(new AccessorMapper($options['get'], $options['set'], $dataMapper));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'get' => null,
            'set' => null,
        ]);

        $resolver->setAllowedTypes('get', ['null', Closure::class]);
        $resolver->setAllowedTypes('set', ['null', Closure::class]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
