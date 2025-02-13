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
 * Provide parameters for a static route.
 *
 * @author Thomas Bibaut <bibaut.t@gmail.com>
 */
interface ParamsProviderInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function provideParams(): iterable;
}
