<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Templating;

use Assetic\Factory\Loader\FormulaLoaderInterface;
use Assetic\Factory\Resource\ResourceInterface;

/**
 * Loads formulae from PHP templates.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class FormulaLoader implements FormulaLoaderInterface
{
    public function load(ResourceInterface $resource)
    {
        $tokens = token_get_all($resource->getContent());

        /**
         * @todo Find and extract asset formulae from calls to the following:
         *
         *  * $view['assetic']->assets(...)
         *  * $view['assetic']->javascripts(...)
         *  * $view['assetic']->stylesheets(...)
         *  * $view->get('assetic')->assets(...)
         *  * $view->get('assetic')->javascripts(...)
         *  * $view->get('assetic')->stylesheets(...)
         *
         * The loader will also need to be aware of debug mode and the default
         * output strings associated with each method.
         */

        return array();
    }
}
