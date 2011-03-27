<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DataMapper;

use Symfony\Component\Form\FormInterface;

interface DataMapperInterface
{
    function mapDataToForms($data, array $forms);

    function mapDataToForm($data, FormInterface $form);

    function mapFormsToData(array $forms, &$data);

    function mapFormToData(FormInterface $form, &$data);
}