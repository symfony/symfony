<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

@trigger_error(sprintf('%s is deprecated since Symfony 3.3 and will be removed in 4.0. Use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass instead.', AddConsoleCommandPass::class), E_USER_DEPRECATED);

use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass as BaseAddConsoleCommandPass;

/**
 * Registers console commands.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass instead.
 */
class AddConsoleCommandPass extends BaseAddConsoleCommandPass
{
}
