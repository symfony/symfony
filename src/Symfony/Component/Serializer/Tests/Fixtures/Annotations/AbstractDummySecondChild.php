<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Tests\Fixtures\DummySecondChildQuux;

class AbstractDummySecondChild extends AbstractDummy
{
    public $baz;

    /** @var DummySecondChildQuux|null */
    public $quux;

    public function __construct($foo = null, $baz = null)
    {
        parent::__construct($foo);

        $this->baz = $baz;
    }

    public function getQuux(): ?DummySecondChildQuux
    {
        return $this->quux;
    }

    public function setQuux(DummySecondChildQuux $quux): void
    {
        $this->quux = $quux;
    }
}
