<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Martin Rademacher <mano@radebatz.net>
 */
class InvalidDummy
{
    /**
     * @var
     */
    public $pub;

    /**
     * @return
     */
    public static function getStat()
    {
        return 'stat';
    }

    /**
     * Foo.
     *
     * @param
     */
    public function setFoo($foo)
    {
    }

    /**
     * Bar.
     *
     * @return
     */
    public function getBar()
    {
        return 'bar';
    }
}
