<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\RequestMatcher\RequestMatcherInterface as BaseRequestMatcherInterface;

@trigger_error('The '.RequestMatcherInterface::class.' class is deprecated since version 3.2 and will be removed in 4.0. Use the '.BaseRequestMatcherInterface::class.' class instead.', E_USER_DEPRECATED);

/**
 * RequestMatcherInterface is an interface for strategies to match a Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RequestMatcherInterface extends BaseRequestMatcherInterface
{
}
