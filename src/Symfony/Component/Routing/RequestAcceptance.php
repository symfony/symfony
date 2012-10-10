<?php

namespace Symfony\Component\Routing;

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
     */
    public function __construct()
    {
        $this->qualities = array();
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
     * Returns quality associated to given value.
     *
     * @param string $value
     *
     * @return float
     */
    public function getQuality($value)
    {
        return isset($this->qualities[$value]) ? $this->qualities[$value] : 0;
    }
}
