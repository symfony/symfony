<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Attribute;

/**
 * Gets the request query parameter of the same name as the argument name.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class RequestQuery implements ArgumentInterface
{
}
