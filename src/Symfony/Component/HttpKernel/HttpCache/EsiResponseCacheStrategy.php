<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This code is partially based on the Rack-Cache library by Ryan Tomayko,
 * which is released under the MIT license.
 * (based on commit 02d2b48d75bcb63cf1c0c7149c077ad256542801)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

@trigger_error('The '.__NAMESPACE__.'\EsiResponseCacheStrategy class is deprecated since version 2.6 and will be removed in 3.0. Use the Symfony\Component\HttpKernel\HttpCache\ResponseCacheStrategy class instead.', E_USER_DEPRECATED);

/**
 * EsiResponseCacheStrategy knows how to compute the Response cache HTTP header
 * based on the different ESI response cache headers.
 *
 * This implementation changes the master response TTL to the smallest TTL received
 * or force validation if one of the ESI has validation cache strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.6, to be removed in 3.0. Use ResponseCacheStrategy instead
 */
class EsiResponseCacheStrategy extends ResponseCacheStrategy implements EsiResponseCacheStrategyInterface
{
}
