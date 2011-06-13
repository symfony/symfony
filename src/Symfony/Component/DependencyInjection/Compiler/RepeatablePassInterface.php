<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

/**
 * Interface that must be implemented by passes that are run as part of an
 * RepeatedPass.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface RepeatablePassInterface extends CompilerPassInterface
{
    /**
     * Sets the RepeatedPass interface.
     *
     * @param RepeatedPass $repeatedPass
     */
    function setRepeatedPass(RepeatedPass $repeatedPass);
}
