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

use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;
use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;

/**
 * GenericProfileData
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 *
 * @deprecated Deprecated since Symfony 2.8, to be removed in Symfony 3.0.
 *             Add the method `getCollectedData` to your DataCollectors,
 *             see {@link Symfony\Component\Profiler\DataCollector\DataCollectorInterface} for more info.
 */
class GenericProfileData implements ProfileDataInterface
{
    private $dataCollector;

    public function __construct(DataCollectorInterface $dataCollector)
    {
        $this->dataCollector = $dataCollector;
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return serialize($this->dataCollector);
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $this->dataCollector = unserialize($serialized);
    }

    public function getName()
    {
        return $this->dataCollector->getName();
    }

    public function __call($name, $arguments)
    {
        if ( method_exists($this->dataCollector, $name)) {
            return call_user_func_array(array($this->dataCollector, $name), $arguments);
        }
        if ( method_exists($this->dataCollector, 'get'.ucfirst($name))) {
            return call_user_func_array(array($this->dataCollector, 'get'.ucfirst($name)), $arguments);
        }
    }
}