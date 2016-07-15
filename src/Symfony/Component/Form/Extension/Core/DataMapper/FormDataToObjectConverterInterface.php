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
interface FormDataToObjectConverterInterface
{
    /**
     * Convert the form data into an object.
     *
     * @param array       $data         Array of form data indexed by fields names.
     * @param object|null $originalData Original data set in the form (after FormEvents::PRE_SET_DATA).
     *
     * @return object|null
     */
    public function convertFormDataToObject(array $data, $originalData);
}
