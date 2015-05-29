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

/**
 * Class EventData
 * @package Symfony\Component\Profiler\ProfileData
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class EventData implements ProfileDataInterface
{
    private $calledListeners;
    private $notCalledListeners;

    public function __construct(array $calledListeners = array(), array $notCalledListeners = array())
    {
        $this->calledListeners = $calledListeners;
        $this->notCalledListeners = $notCalledListeners;
    }

    /**
     * Gets the called listeners.
     *
     * @return array An array of called listeners
     *
     * @see TraceableEventDispatcherInterface
     */
    public function getCalledListeners()
    {
        return $this->calledListeners;
    }

    /**
     * Gets the not called listeners.
     *
     * @return array An array of not called listeners
     *
     * @see TraceableEventDispatcherInterface
     */
    public function getNotCalledListeners()
    {
        return $this->notCalledListeners;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'events';
    }
}