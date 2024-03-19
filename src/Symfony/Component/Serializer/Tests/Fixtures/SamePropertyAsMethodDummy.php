<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

class SamePropertyAsMethodDummy
{
    private $freeTrial;
    private $hasSubscribe;
    private $getReady;
    private $isActive;

    public function __construct($freeTrial, $hasSubscribe, $getReady, $isActive)
    {
        $this->freeTrial = $freeTrial;
        $this->hasSubscribe = $hasSubscribe;
        $this->getReady = $getReady;
        $this->isActive = $isActive;
    }

    public function getFreeTrial()
    {
        return $this->freeTrial;
    }

    public function hasSubscribe()
    {
        return $this->hasSubscribe;
    }

    public function getReady()
    {
        return $this->getReady;
    }

    public function isActive()
    {
        return $this->isActive;
    }
}
