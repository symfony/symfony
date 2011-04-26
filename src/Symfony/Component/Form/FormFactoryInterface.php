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

interface FormFactoryInterface
{
    function create($type, $data = null, array $options = array());

    function createNamed($type, $name, $data = null, array $options = array());

    function createForProperty($class, $property, $data = null, array $options = array());

    function createBuilder($type, $data = null, array $options = array());

    function createNamedBuilder($type, $name, $data = null, array $options = array());

    function createBuilderForProperty($class, $property, $data = null, array $options = array());
}
