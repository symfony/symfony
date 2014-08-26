<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\TokenParser\DumpTokenParser;

/**
 * Provides integration of the dump() function with Twig.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpExtension extends \Twig_Extension
{
    public function getTokenParsers()
    {
        return array(new DumpTokenParser());
    }

    public function getName()
    {
        return 'dump';
    }
}
