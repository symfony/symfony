<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldInterface;

interface FieldConfigInterface
{
    function configure(FieldInterface $field, array $options);

    function createInstance($name);

    function getDefaultOptions(array $options);

    function getParent(array $options);

    function getIdentifier();
}