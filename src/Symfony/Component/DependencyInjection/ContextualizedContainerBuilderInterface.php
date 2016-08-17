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
 * Implemented by a ContainerBuilder sharing a context during build time.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
interface ContextualizedContainerBuilderInterface extends ContainerInterface
{
    /**
     * @return Context
     */
    public function getContext();
}
