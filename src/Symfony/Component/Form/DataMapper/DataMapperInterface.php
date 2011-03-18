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

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FormInterface;

interface DataMapperInterface
{
    function createEmptyData();

    function mapDataToForm(&$data, FormInterface $form);

    function mapDataToField(&$data, FieldInterface $field);

    function mapFormToData(FormInterface $form, &$data);

    function mapFieldToData(FieldInterface $field, &$data);
}