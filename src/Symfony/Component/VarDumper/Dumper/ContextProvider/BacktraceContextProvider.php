<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper\ContextProvider;

use Symfony\Component\VarDumper\Caster\TraceStub;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\VarDumperOptions;

/**
 * Provides the debug stacktrace of the VarDumper call.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class BacktraceContextProvider implements ContextProviderInterface
{
    private const BACKTRACE_CONTEXT_PROVIDER_DEPTH = 4;

    public function __construct(
        private readonly bool|int $limit,
        private ?ClonerInterface $cloner
    ) {
        $this->cloner ??= new VarCloner();
    }

    public function getContext(): ?array
    {
        if (false === $this->limit) {
            return [];
        }

        $context = [];
        $traces = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);

        for ($i = self::BACKTRACE_CONTEXT_PROVIDER_DEPTH; $i < \count($traces); ++$i) {
            if (VarDumperOptions::class === ($traces[$i + 1]['class'] ?? null)) {
                continue;
            }

            $context[] = $traces[$i];

            if ($this->limit === \count($context)) {
                break;
            }
        }

        $stub = new TraceStub($context);

        return ['backtrace' => $this->cloner->cloneVar($stub->value)];
    }
}
