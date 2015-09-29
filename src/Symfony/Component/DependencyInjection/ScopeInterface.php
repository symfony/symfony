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

/**
 * Scope Interface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ScopeInterface
{
    public function getName();

    public function getParentName();
}
