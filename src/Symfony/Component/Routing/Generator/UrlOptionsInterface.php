<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator;

/**
 * UrlOptionsInterface is the interface that add support for getting options of a route.
 *
 * @author Grégoire Passault <g.passault@gmail.com>
 */
interface UrlOptionsInterface
{
    /**
     * Getting options of the given route.
     *
     * @param $name The route name
     *
     * @return array The route options
     */
    public function getOptions($name);
}
