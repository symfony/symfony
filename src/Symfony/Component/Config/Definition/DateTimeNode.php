<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class DateTimeNode extends VariableNode
{
    private $timezone;
    private $format;

    public function __construct($name, NodeInterface $parent = null, $format = null, \DateTimeZone $timezone = null)
    {
        parent::__construct($name, $parent);

        $this->timezone = $timezone;
        $this->format = $format;
    }

    /**
     * @return \DateTimeZone|null
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @return string|null
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateType($value)
    {
        if (!is_int($value) && !is_string($value) && !$this->isValueEmpty($value)) {
            $ex = new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected int, string or empty, but got %s.',
                $this->getPath(),
                gettype($value)
            ));

            $this->setHints($ex);

            throw $ex;
        }

        if (is_string($value) && null !== $this->format) {
            $date = \DateTime::createFromFormat($this->format, $value);
            // Ensure date validity against format
            if (false !== $date && $value === $date->format($this->format)) {
                return;
            }

            $ex = new InvalidConfigurationException(sprintf(
                'Invalid value for path "%s". Unable to parse datetime string "%s" according to specified "%s" format.',
                $this->getPath(),
                $value,
                $this->format

            ));

            $this->setHints($ex);

            throw $ex;
        }

        if (is_string($value) && false === strtotime($value)) {
            $ex = new InvalidConfigurationException(sprintf(
                'Invalid value for path "%s". Unable to interpret datetime string "%s" as a datetime. Please provide a "strtotime" understandable datetime string.',
                $this->getPath(),
                $value

            ));

            $this->setHints($ex);

            throw $ex;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function finalizeValue($value)
    {
        $value = parent::finalizeValue($value);

        if ($this->isValueEmpty($value)) {
            return;
        }

        if (is_int($value)) {
            return new \DateTime("@$value");
        }

        if (null !== $this->format) {
            // https://bugs.php.net/bug.php?id=68669
            return null !== $this->timezone ? \DateTime::createFromFormat($this->format, $value, $this->timezone) : \DateTime::createFromFormat($this->format, $value);
        }

        return new \DateTime($value, $this->timezone);
    }

    private function setHints(InvalidConfigurationException $ex)
    {
        if ($hint = $this->getInfo()) {
            $ex->addHint($hint);
        }

        $ex->setPath($this->getPath());
    }
}
