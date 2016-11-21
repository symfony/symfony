<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\DateTimeNode;

/**
 * This class provides a fluent interface for defining a DateTime node.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class DateTimeNodeDefinition extends VariableNodeDefinition
{
    private $format;
    private $timezone;

    /**
     * @param string $format
     *
     * @return DateTimeNodeDefinition
     */
    public function format($format)
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @param \DateTimeZone|string $timezone
     *
     * @return DateTimeNodeDefinition
     */
    public function timezone($timezone)
    {
        if (!$timezone instanceof \DateTimeZone && false === $timezone = @timezone_open($timezone)) {
            throw new \InvalidArgumentException('->timezone() must be called with a valid timezone identifier or a "\DateTimeZone" instance.');
        }

        $this->timezone = $timezone;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return DateTimeNode
     */
    protected function instantiateNode()
    {
        return new DateTimeNode($this->name, $this->parent, $this->format, $this->timezone);
    }
}
