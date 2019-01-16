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
    const SERVICE_BIND = 0;
    const DEFAULT_BIND = 1;
    const INSTANCE_BIND = 2;

    const MESSAGES = [
        1 => 'under "_defaults"',
        2 => 'under "_instanceof"',
    ];

    private static $sequence = 0;

    private $value;
    private $identifier;
    private $used;
    private $type;
    private $file;

    public function __construct($value, $type = 0, $file = null)
    {
        $this->value = $value;
        $this->identifier = ++self::$sequence;
        $this->type = (int) $type;
        $this->file = (string) $file;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return [$this->value, $this->identifier, $this->used, $this->type, $this->file];
    }

    /**
     * {@inheritdoc}
     */
    public function setValues(array $values)
    {
        list($this->value, $this->identifier, $this->used) = $values;
    }
}
