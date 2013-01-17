<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

/**
 * A matcher implementing RequestMatcherInterface indicates that it also accepts
 * a {@link \Symfony\Component\HttpFoundation\Request} object as parameter of the
 * match method. In that way it is a marker interface that widens the accepted
 * range of the extended UrlMatcherInterface::match method.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RequestMatcherInterface extends UrlMatcherInterface
{

}
