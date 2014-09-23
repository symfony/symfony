<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

/**
 * EsiListener adds a Surrogate-Control HTTP header when the Response needs to be parsed for ESI.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated Deprecated since version 2.6, to be removed in 3.0. Use SurrogateListener instead
 */
class EsiListener extends SurrogateListener
{
}
