<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FieldBuilder;

interface FieldTypeInterface
{
    function configure(FieldBuilder $builder, array $options);

    function createBuilder(array $options);

    function getDefaultOptions(array $options);

    function getParent(array $options);

    function getName();
}