<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Tests\Fixtures;

/**
 * Entity with an id getter.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class EntityImplementingToString
{
    public $id;
    private $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function __toString()
    {
        return (string) $this->name;
    }
}
