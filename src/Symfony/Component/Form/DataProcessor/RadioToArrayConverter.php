<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DataProcessor;

use Symfony\Component\Form\FieldInterface;

/**
 * Takes care of converting the input from a single radio button
 * to an array.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class RadioToArrayConverter implements DataProcessorInterface
{
    public function processData($data)
    {
        return count((array)$data) === 0 ? array() : array($data => true);
    }
}