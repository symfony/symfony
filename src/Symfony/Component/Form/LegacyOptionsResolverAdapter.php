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
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * This class is only present for backwards compatibility and should not be
 * used outside the Form component. It will be removed in Symfony 3.0.
 *
 * @internal
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class LegacyOptionsResolverAdapter extends OptionsResolver
{
    private $wrappedOptionsResolver;

    public function __construct(OptionsResolverInterface $wrappedOptionsResolver)
    {
        $this->wrappedOptionsResolver = $wrappedOptionsResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults(array $defaultValues)
    {
        $this->wrappedOptionsResolver->setDefaults($defaultValues);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceDefaults(array $defaultValues)
    {
        $this->wrappedOptionsResolver->replaceDefaults($defaultValues);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptional(array $optionNames)
    {
        $this->wrappedOptionsResolver->setOptional($optionNames);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequired($optionNames)
    {
        $this->wrappedOptionsResolver->setRequired($optionNames);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAllowedValues($allowedValues)
    {
        $this->wrappedOptionsResolver->setAllowedValues($allowedValues);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addAllowedValues($allowedValues)
    {
        $this->wrappedOptionsResolver->addAllowedValues($allowedValues);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAllowedTypes($allowedTypes)
    {
        $this->wrappedOptionsResolver->setAllowedTypes($allowedTypes);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addAllowedTypes($allowedTypes)
    {
        $this->wrappedOptionsResolver->addAllowedTypes($allowedTypes);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setNormalizers(array $normalizers)
    {
        $this->wrappedOptionsResolver->setNormalizers($normalizers);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isKnown($option)
    {
        return $this->wrappedOptionsResolver->isKnown($option);
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired($option)
    {
        return $this->wrappedOptionsResolver->isRequired($option);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(array $options = array())
    {
        return $this->wrappedOptionsResolver->resolve($options);
    }
}
