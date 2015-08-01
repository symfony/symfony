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
 *
 * @api
 *
 * @deprecated since version 2.8, to be removed in 3.0.
 */
interface ScopeInterface
{
    /**
     * @api
     */
    public function getName();

    /**
     * @api
     */
    public function getParentName();
}
