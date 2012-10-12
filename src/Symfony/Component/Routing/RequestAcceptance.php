<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * Holds information about an Accept-* header of hte current request.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class RequestAcceptance
{
    /**
     * Indexed array: value => quality.
     *
     * @var array
     */
    private $qualities;

    /**
     * Constructor.
     *
     * @param string $header
     */
    public function __construct($header = '')
    {
        $this->qualities = AcceptHeader::split($header);
    }

    /**
     * Filters value with given regex.
     *
     * @param string $regex
     *
     * @return RequestAcceptance
     */
    public function filter($regex)
    {
        $acceptance = new self();
        foreach ($this->qualities as $value => $quality) {
            if (preg_match(sprintf('~%s~i', $regex), $value)) {
                $acceptance->add($value, $quality);
            }
        }

        return $acceptance;
    }

    /**
     * Adds a value.
     *
     * @param string    $value
     * @param float|int $quality
     */
    public function add($value, $quality = 1)
    {
        $this->qualities[$value] = $quality;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public function hasValue($value)
    {
        return isset($this->qualities[$value]);
    }

    /**
     * Returns value with highest quality.
     */
    public function getBestValue()
    {
        arsort($this->qualities, SORT_NUMERIC);

        return key($this->qualities);
    }

    /**
     * Returns the values.
     *
     * @return array
     */
    public function getValues()
    {
        return array_keys($this->qualities);
    }

    /**
     * Applies a closure on values.
     *
     * @param \Closure $closure
     *
     * @return array
     */
    public function mapValues(\Closure $closure)
    {
        $this->qualities = array_combine(array_map($closure, array_keys($this->qualities)), array_values($this->qualities));
    }
}
