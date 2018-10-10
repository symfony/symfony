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

use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractTypeExtension implements FormTypeExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 4.2, use getExtendedTypes() instead.
     */
    public function getExtendedType()
    {
        if (!method_exists($this, 'getExtendedTypes')) {
            throw new LogicException(sprintf('You need to implement the static getExtendedTypes() method when implementing the %s in %s.', FormTypeExtensionInterface::class, static::class));
        }

        @trigger_error(sprintf('The %s::getExtendedType() method is deprecated since Symfony 4.2 and will be removed in 5.0. Use getExtendedTypes() instead.', \get_class($this)), E_USER_DEPRECATED);

        foreach (static::getExtendedTypes() as $extendedType) {
            return $extendedType;
        }
    }
}
