<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\Util\PropertyPath;

class MagicianWrapper
{
    private $foobar;
    protected $wrappedObject;

    public function __construct($wrappedObject)
    {
        $this->wrappedObject = $wrappedObject;
    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function __get($property)
    {
        $path = new PropertyPath($property);

        return $path->getValue($this->wrappedObject);
    }
}