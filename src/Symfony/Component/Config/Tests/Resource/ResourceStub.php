<?php
namespace Symfony\Component\Config\Tests\Resource;

use Symfony\Component\Config\Resource\ResourceInterface;

class ResourceStub implements ResourceInterface
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
