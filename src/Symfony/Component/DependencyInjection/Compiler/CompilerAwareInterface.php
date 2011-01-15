<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

/**
 * This interface can be implemented by passes that need to access the
 * compiler.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface CompilerAwareInterface
{
    function setCompiler(Compiler $compiler);
}