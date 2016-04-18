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
 * Entity with a public id.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class PublicEntity
{
    public $id;

    public function __construct($id)
    {
        $this->id = $id;
    }
}
