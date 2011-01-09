<?php

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