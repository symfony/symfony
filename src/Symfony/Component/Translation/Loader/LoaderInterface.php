<?php

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\MessageCatalogue;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderInterface is the interface implemented by all translation loaders.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoaderInterface
{
    /**
     * Loads a locale.
     *
     * @param  mixed  $resource A resource
     * @param  string $locale   A locale
     * @param  string $domain   The domain
     *
     * @return MessageCatalogue A MessageCatalogue instance
     */
    function load($resource, $locale, $domain = 'messages');
}
