<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory\Loader;

use Assetic\Factory\Loader\FormulaLoaderInterface;
use Assetic\Factory\Resource\ResourceInterface;
use Symfony\Bundle\AsseticBundle\Factory\Resource\ConfigurationResource;

/**
 * Loads configured formulae.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class ConfigurationLoader implements FormulaLoaderInterface
{
    public function load(ResourceInterface $resource)
    {
        return $resource instanceof ConfigurationResource ? $resource->getContent() : array();
    }
}
