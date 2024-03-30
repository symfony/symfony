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

use Symfony\Component\Serializer\Annotation\SerializedName;

class SamePropertyAsMethodWithMethodSerializedNameDummy
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

    #[SerializedName('free_trial_method')]
    public function getFreeTrial()
    {
        return $this->freeTrial;
    }

    #[SerializedName('has_subscribe_method')]
    public function hasSubscribe()
    {
        return $this->hasSubscribe;
    }

    #[SerializedName('get_ready_method')]
    public function getReady()
    {
        return $this->getReady;
    }

    #[SerializedName('is_active_method')]
    public function isActive()
    {
        return $this->isActive;
    }
}
