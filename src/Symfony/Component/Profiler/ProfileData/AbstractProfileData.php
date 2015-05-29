<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\ProfileData;


use Symfony\Component\Profiler\DataCollector\Util\ValueExporter;

/**
 * Class AbstractProfileData
 * @package Symfony\Component\Profiler\ProfileData
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
abstract class AbstractProfileData implements ProfileDataInterface, \Serializable {

    protected $data;

    /**
     * @var ValueExporter
     */
    private $valueExporter;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function serialize()
    {
        return serialize($this->data);
    }

    public function unserialize($data)
    {
        $this->data = unserialize($data);
    }

    /**
     * Converts a PHP variable to a string.
     *
     * @param mixed $var A PHP variable
     *
     * @return string The string representation of the variable
     */
    protected function varToString($var)
    {
        if (null === $this->valueExporter) {
            $this->valueExporter = new ValueExporter();
        }

        return $this->valueExporter->exportValue($var);
    }
}