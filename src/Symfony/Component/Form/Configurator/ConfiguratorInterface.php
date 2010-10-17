<?php

namespace Symfony\Component\Form\Configurator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

interface ConfiguratorInterface
{
    function initialize($object);

    function getClass($fieldName);

    function getOptions($fieldName);

    function isRequired($fieldName);
}