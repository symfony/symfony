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

interface DataMapperInterface
{
    /**
     * @param dataClass $data
     * @param array     $forms
     *
     * @throws Exception\UnexpectedTypeException if the type of the data parameter is not supported
     */
    function mapDataToForms($data, array $forms);

    function mapFormsToData(array $forms, &$data);
}
