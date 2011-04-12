<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;

/**
 * Used to format logging messages during the compilation.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class LoggingFormatter
{
    public function formatRemoveDefinition(CompilerPassInterface $pass, $id, $reason)
    {
        return $this->format($pass, sprintf('Removed definition "%s"; reason: %s', $id, $reason));
    }

    public function formatInlineDefinition(CompilerPassInterface $pass, $id, $target)
    {
        return $this->format($pass, sprintf('Inlined definition "%s" to "%s".', $id, $target));
    }

    public function format(CompilerPassInterface $pass, $message)
    {
        return sprintf('%s: %s', get_class($pass), $message);
    }
}