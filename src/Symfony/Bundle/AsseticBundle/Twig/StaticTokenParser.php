<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Twig;

use Assetic\Extension\Twig\TokenParser;

/**
 * Parses the {% assets %} tag.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class StaticTokenParser extends TokenParser
{
    static protected function createNode(\Twig_NodeInterface $body, array $sourceUrls, $targetUrl, array $filterNames, $assetName, $debug = false, $lineno = 0, $tag = null)
    {
        return new StaticNode($body, $sourceUrls, $targetUrl, $filterNames, $assetName, $debug, $lineno, $tag);
    }
}
