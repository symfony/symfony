<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataMapper;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
interface ObjectToFormDataConverterInterface
{
    /**
     * Convert given object to form data.
     *
     * @param object|null $object The object to map to the form.
     *
     * @return array The array of form data indexed by fields names.
     */
    public function convertObjectToFormData($object);
}
