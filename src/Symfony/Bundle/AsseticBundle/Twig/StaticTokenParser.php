<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Twig;

use Assetic\Extension\Twig\AsseticTokenParser;

/**
 * Parses an Assetic tag.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class StaticTokenParser extends AsseticTokenParser
{
    static protected function createNode(\Twig_NodeInterface $body, array $inputs, array $filters, array $attributes, $lineno = 0, $tag = null)
    {
        return new StaticNode($body, $inputs, $filters, $attributes, $lineno, $tag);
    }
}
