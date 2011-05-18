<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory\Resource;

use Assetic\Factory\Resource\ResourceInterface;

/**
 * A configured resource.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class ConfigurationResource implements ResourceInterface
{
    private $formulae;

    public function __construct(array $formulae)
    {
        $this->formulae = $formulae;
    }

    public function isFresh($timestamp)
    {
        return true;
    }

    public function getContent()
    {
        return $this->formulae;
    }

    public function __toString()
    {
        return 'symfony';
    }
}
