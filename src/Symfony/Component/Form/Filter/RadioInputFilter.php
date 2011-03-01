<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Filter;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Filters;

/**
 * Takes care of converting the input from a single radio button
 * to an array.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class RadioInputFilter implements FilterInterface
{
    public function filterBoundDataFromClient($data)
    {
        return count((array)$data) === 0 ? array() : array($data => true);
    }

    public function getSupportedFilters()
    {
        return Filters::filterBoundDataFromClient;
    }
}