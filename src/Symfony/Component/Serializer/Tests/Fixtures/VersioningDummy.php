<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Since;
use Symfony\Component\Serializer\Annotation\Until;

class VersioningDummy
{
    /**
     * @Since("1.0")
     * @Until("1.1.9")
     */
    public $foo;

    public $bar;

    /**
     * @Since("0.9")
     */
    public $username;

    /**
     * @Since("1.1.2")
     */
    public function getBar()
    {
        return $this->foo;
    }

    /**
     * @Until("1.3")
     */
    public function getUsername()
    {
        return $this->username;
    }
}
