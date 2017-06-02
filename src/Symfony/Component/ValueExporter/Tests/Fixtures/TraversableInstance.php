<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Tests\Fixtures;

/**
 * TraversableInstance.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class TraversableInstance implements \IteratorAggregate
{
    public $property1 = 'value1';
    public $property2 = 'value2';

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this);
    }
}
