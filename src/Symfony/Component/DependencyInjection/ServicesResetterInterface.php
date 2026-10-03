<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Resets the services tagged with "kernel.reset", e.g. between two requests or messages.
 */
interface ServicesResetterInterface extends ResetInterface
{
}
