<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper;

use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\BacktraceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextProvider\CliContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextProvider\RequestContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\ServerDumper;

// Load the global dump() function
require_once __DIR__.'/Resources/functions/dump.php';

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class VarDumper
{
    private const OPTIONS_ENVIRONMENT_KEY = 'VAR_DUMPER_OPTIONS';

    public const AVAILABLE_OPTIONS = [
        '_format',
        '_trace',
        '_max_items',
        '_min_depth',
        '_max_string',
        '_max_depth',
        '_max_items_per_depth',
        '_theme',
        '_flags',
        '_charset',
    ];

    /**
     * @var callable|null
     */
    private static $handler;

    private static ?string $prevOptionsHash = null;

    private static bool $manualHandlerRegister = false;

    /**
     * @param array{
     *     '_format'?: ?string,
     *     '_trace'?: bool|int|null,
     *     '_max_items'?: ?int,
     *     '_min_depth'?: ?int,
     *     '_max_string'?: ?int,
     *     '_max_depth'?: ?int,
     *     '_max_items_per_depth'?: ?int,
     *     '_theme'?: ?string,
     *     '_flags'?: int-mask-of<AbstractDumper::DUMP_*>|null,
     *     '_charset'?: ?string,
     * } $options The options to configure the dump output
     */
    public static function dump(mixed $var, ?string $label = null/* , array $options = [] */): mixed
    {
        $options = 3 <= \func_num_args() ? func_get_arg(2) : [];

        parse_str($_SERVER[self::OPTIONS_ENVIRONMENT_KEY] ?? '', $envOptions);
        $options = array_replace(array_fill_keys(self::AVAILABLE_OPTIONS, null), array_merge($options, $envOptions));

        if (self::requiresRegister($options)) {
            self::register($options);
        }

        return (self::$handler)($var, $label, $options);
    }

    public static function setHandler(?callable $callable): ?callable
    {
        $prevHandler = self::$handler;

        // Prevent replacing the handler with expected format as soon as the env var was set:
        if (isset($_SERVER['VAR_DUMPER_FORMAT'])) {
            return $prevHandler;
        }

        self::$handler = $callable;
        self::$manualHandlerRegister = true;

        return $prevHandler;
    }

    private static function register(array $options): void
    {
        self::$prevOptionsHash = self::getOptionsHash($options);

        $cloner = self::createVarClonerWithOptions($options);
        $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);

        $format = $_SERVER['VAR_DUMPER_FORMAT'] ?? $options['_format'];
        $charset = $options['_charset'];
        $flags = $options['_flags'] ?? 0;

        switch (true) {
            case 'html' === $format:
                $dumper = new HtmlDumper(null, $charset, $flags);
                $dumper->setTheme($options['_theme'] ?? 'dark');
                break;
            case 'cli' === $format:
                $dumper = new CliDumper(null, $charset, $flags);
                break;
            case 'server' === $format:
            case $format && 'tcp' === parse_url($format, \PHP_URL_SCHEME):
                $host = 'server' === $format ? $_SERVER['VAR_DUMPER_SERVER'] ?? '127.0.0.1:9912' : $format;
                $dumper = \in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) ?
                    new CliDumper(null, $charset, $flags) : new HtmlDumper(null, $charset, $flags);
                $dumper = new ServerDumper($host, $dumper, self::getDefaultContextProviders($cloner));
                break;
            default:
                $dumper = \in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) ?
                    new CliDumper(null, $charset, $flags) : new HtmlDumper(null, $charset, $flags);
        }

        if (!$dumper instanceof ServerDumper) {
            $dumper = new ContextualizedDumper($dumper, [
                new SourceContextProvider(),
                new BacktraceContextProvider($options['_trace'] ?? false, $cloner),
            ]);
        }

        self::$handler = function ($var, ?string $label = null, array $options = []) use ($cloner, $dumper) {
            $var = self::cloneVarWithOptions($cloner, $var, $options);

            if (null !== $label) {
                $var = $var->withContext(['label' => $label]);
            }

            $var = $var->withContext(array_merge($var->getContext(), ['options' => $options]));
            $dumper->dump($var);
        };
    }

    private static function getDefaultContextProviders(ClonerInterface $cloner): array
    {
        $contextProviders = [];

        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && class_exists(Request::class)) {
            $requestStack = new RequestStack();
            $requestStack->push(Request::createFromGlobals());
            $contextProviders['request'] = new RequestContextProvider($requestStack);
        }

        $fileLinkFormatter = class_exists(FileLinkFormatter::class) ? new FileLinkFormatter(null, $requestStack ?? null) : null;

        return $contextProviders + [
            'cli' => new CliContextProvider(),
            'source' => new SourceContextProvider(null, null, $fileLinkFormatter),
            'backtrace' => new BacktraceContextProvider(false, $cloner),
        ];
    }

    private static function cloneVarWithOptions(ClonerInterface $cloner, mixed $var, array $options): Data
    {
        $var = $cloner->cloneVar($var);

        if (null !== $maxDepth = $options['_max_depth']) {
            $var = $var->withMaxDepth($maxDepth);
        }

        if (null !== $maxItemsPerDepth = $options['_max_items_per_depth']) {
            $var = $var->withMaxItemsPerDepth($maxItemsPerDepth);
        }

        return $var;
    }

    private static function requiresRegister(array $options): bool
    {
        return null === self::$handler || (self::getOptionsHash($options) !== self::$prevOptionsHash && !self::$manualHandlerRegister);
    }

    private static function getOptionsHash(array $options): string
    {
        return md5(serialize($options));
    }

    private static function createVarClonerWithOptions(array $options): ClonerInterface
    {
        $cloner = new VarCloner();

        if (null !== $maxItems = $options['_max_items']) {
            $cloner->setMaxItems($maxItems);
        }

        if (null !== $minDepth = $options['_min_depth']) {
            $cloner->setMinDepth($minDepth);
        }

        if (null !== $maxString = $options['_max_string']) {
            $cloner->setMaxString($maxString);
        }

        return $cloner;
    }
}
