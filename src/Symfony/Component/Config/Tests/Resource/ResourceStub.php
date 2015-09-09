<?php

namespace Symfony\Component\Config\Tests\Resource;

use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

class ResourceStub implements SelfCheckingResourceInterface
{
    private $fresh = true;

    public function setFresh($isFresh)
    {
        $this->fresh = $isFresh;
    }

    public function __toString() {
        return 'stub';
    }

    public function isFresh($timestamp)
    {
        return $this->fresh;
    }

    public function getResource()
    {
        return 'stub';
    }
}
