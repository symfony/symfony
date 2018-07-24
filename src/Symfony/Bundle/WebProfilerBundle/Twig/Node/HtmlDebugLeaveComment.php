<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Twig\Node;

use Twig\Node\Node;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class HtmlDebugLeaveComment extends Node implements \Twig_NodeOutputInterface
{
    public function __construct($type, $hash)
    {
        parent::__construct(array(), array('type' => $type, 'hash' => $hash));
    }

    public function compile(\Twig_Compiler $compiler)
    {
        $compiler
            ->write('echo ')
            ->string(sprintf("<!--TWIG-END: %s %s-->\n", $this->getAttribute('type'), $this->getAttribute('hash')))
            ->raw(";\n")
        ;
    }
}
