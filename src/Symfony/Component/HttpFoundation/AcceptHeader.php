<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Accept-* HTTP header parser.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class AcceptHeader
{
    /**
     * @var array
     */
    private $values;

    /**
     * @param string $header
     * @return AcceptHeader
     */
    public static function create($header)
    {
        return new static($header);
    }

    /**
     * @param string $header
     */
    public function __construct($header)
    {
        $this->values = array();
        foreach (explode(',', $header) as $value) {
            $properties = explode(';', $value);
            if (!$name = trim(array_shift($properties))) {
                continue;
            }
            $this->values[$name] = array();
            foreach ($properties as $property) {
                $bits = explode('=', $property);
                $this->values[$name][trim($bits[0])] = isset($bits[1]) ? trim($bits[1]) : null;
            }
        }
    }

    /**
     * @param array $defaults
     *
     * @return AcceptHeader
     */
    public function setDefaults(array $defaults)
    {
        foreach ($this->values as $name => $properties) {
            $this->values[$name] = array_merge($defaults, $properties);
        }

        return $this;
    }

    /**
     * @param string $property
     *
     * @return AcceptHeader
     */
    public function sort($property)
    {
        uasort($this->values, function (array $a, array $b) use ($property) {
            return strcmp($a[$property], $b[$property]);
        });

        return $this;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return array_keys($this->values);
    }

    /**
     * @param $property
     *
     * @return array
     */
    public function getHash($property)
    {
        $hash = array();
        foreach ($this->values as $name => $properties) {
            if (!isset($properties[$property])) {
                continue;
            }
            foreach ($properties as $key => $value) {
                $name.= $key === $property ? '' : sprintf(';%s=%s', $key, $value);
            }
            $hash[$name] = is_numeric($properties[$property]) ? (float) $properties[$property] : $properties[$property];
        }

        return $hash;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->values;
    }
}
