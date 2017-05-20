<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Represents a service wrapped in a memoizing closure.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ServiceClosureArgument implements ArgumentInterface
{
    private $values;

    public function __construct(Reference $reference)
    {
        $this->values = array($reference);
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * {@inheritdoc}
     */
    public function setValues(array $values)
    {
        if (array(0) !== array_keys($values) || !($values[0] instanceof Reference || null === $values[0])) {
            throw new InvalidArgumentException('A ServiceClosureArgument must hold one and only one Reference.');
        }

        $this->values = $values;
    }
}
