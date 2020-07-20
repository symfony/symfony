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

class AbstractDummyFirstChild extends AbstractDummy
{
    public $bar;

    /** @var DummyFirstChildQuux|null */
    public $quux;

    public function __construct($foo = null, $bar = null)
    {
        parent::__construct($foo);

        $this->bar = $bar;
    }

    public function getQuux(): ?DummyFirstChildQuux
    {
        return $this->quux;
    }

    public function setQuux(DummyFirstChildQuux $quux): void
    {
        $this->quux = $quux;
    }
}
