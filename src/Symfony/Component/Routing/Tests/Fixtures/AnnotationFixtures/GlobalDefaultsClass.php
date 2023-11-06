<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

/**
 * @Route("/defaults", methods="GET", schemes="https", locale="g_locale", format="g_format")
 */
class GlobalDefaultsClass
{
    /**
     * @Route("/specific-locale", name="specific_locale", locale="s_locale")
     */
    public function locale()
    {
    }

    /**
     * @Route("/specific-format", name="specific_format", format="s_format")
     */
    public function format()
    {
    }

    /**
     * @Route("/redundant-method", name="redundant_method", methods="GET")
     */
    public function redundantMethod()
    {
    }

    /**
     * @Route("/redundant-scheme", name="redundant_scheme", schemes="https")
     */
    public function redundantScheme()
    {
    }
}
