<?php

namespace Symfony\Component\DependencyInjection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * TaggedContainerInterface is the interface implemented when a container knows how to deals with tags.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface TaggedContainerInterface
{
    /**
     * Returns service ids for a given tag.
     *
     * @param string $name The tag name
     *
     * @return array An array of tags
     */
    function findTaggedServiceIds($name);
}
