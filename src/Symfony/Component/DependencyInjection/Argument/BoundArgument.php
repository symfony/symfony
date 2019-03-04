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

/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
final class BoundArgument implements ArgumentInterface
{
    private static $sequence = 0;

    private $value;
    private $identifier;
    private $used;

    public function __construct($value, bool $trackUsage = true)
    {
        $this->value = $value;
        if ($trackUsage) {
            $this->identifier = ++self::$sequence;
        } else {
            $this->used = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return [$this->value, $this->identifier, $this->used];
    }

    /**
     * {@inheritdoc}
     */
    public function setValues(array $values)
    {
        list($this->value, $this->identifier, $this->used) = $values;
    }
}
