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

use Symfony\Component\Form\FormInterface;

interface FormFactoryInterface
{
    function createBuilder($type, $name = null, array $options = array());

    function createBuilderForProperty($class, $property, array $options = array());

    function create($type, $name = null, array $options = array());

    function createForProperty($class, $property, array $options = array());

    function createTemplateContext(FormInterface $form);
}
