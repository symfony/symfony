<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * Lets value resolvers tell when an argument could be under their watch but failed to be resolved.
 *
 * Throwing this exception inside `ValueResolverInterface::resolve` does not interrupt the value resolvers chain.
 *
 * @deprecated
 */
class NearMissValueResolverException extends \Symfony\Component\ArgumentResolver\Exception\NearMissValueResolverException
{
}
