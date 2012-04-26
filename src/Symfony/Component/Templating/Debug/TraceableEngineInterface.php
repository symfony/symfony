<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Debug;

/**
 * @author Pierre Minnieur <pierre.minnieur@sensiolabs.de>
 */
interface TraceableEngineInterface
{
    /**
     * Gets the rendered templates.
     *
     * @return array An array of rendered templates
     */
    function getRenderedTemplates();
}
