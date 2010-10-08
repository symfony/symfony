<?php

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Resource\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * PhpFileLoader loads translations from PHP files returning an array of translations.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpFileLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     */
    function load($resource, $locale, $domain = 'messages')
    {
        $catalogue = new MessageCatalogue($locale);
        $catalogue->addMessages(require($resource), $domain);
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }
}
