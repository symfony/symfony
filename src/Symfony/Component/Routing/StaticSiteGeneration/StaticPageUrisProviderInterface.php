<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\StaticSiteGeneration;

/**
 * List URIs considered as static pages.
 *
 * @author Thomas Bibaut <bibaut.t@gmail.com>
 */
interface StaticPageUrisProviderInterface
{
    /**
     * @return iterable<string>
     */
    public function provide(): iterable;
}
