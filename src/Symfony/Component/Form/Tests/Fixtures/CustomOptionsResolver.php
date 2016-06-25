<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CustomOptionsResolver implements OptionsResolverInterface
{
    public function setDefaults(array $defaultValues)
    {
    }

    public function replaceDefaults(array $defaultValues)
    {
    }

    public function setOptional(array $optionNames)
    {
    }

    public function setRequired($optionNames)
    {
    }

    public function setAllowedValues($allowedValues)
    {
    }

    public function addAllowedValues($allowedValues)
    {
    }

    public function setAllowedTypes($allowedTypes)
    {
    }

    public function addAllowedTypes($allowedTypes)
    {
    }

    public function setNormalizers(array $normalizers)
    {
    }

    public function isKnown($option)
    {
    }

    public function isRequired($option)
    {
    }

    public function resolve(array $options = array())
    {
    }
}
