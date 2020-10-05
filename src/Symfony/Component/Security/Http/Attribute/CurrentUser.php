<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Attribute;

use Symfony\Component\HttpKernel\Attribute\ArgumentInterface;

/**
 * Indicates that a controller argument should receive the current logged user.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class CurrentUser implements ArgumentInterface
{
}
