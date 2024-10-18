<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\LazyClosure;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\CheckCircularReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\ServiceReferenceGraphNode;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\EnvParameterException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\LazyServiceDumper;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\NullDumper;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator as BaseServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\DependencyInjection\Variable;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * PhpDumper dumps a service container as a PHP class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PhpDumper extends Dumper
{
    /**
     * Characters that might appear in the generated variable name as first character.
     */
    public const FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * Characters that might appear in the generated variable name as any but the first character.
     */
    public const NON_FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_';

    /** @var \SplObjectStorage<Definition, Variable>|null */
    private ?\SplObjectStorage $definitionVariables = null;
    private ?array $referenceVariables = null;
    private int $variableCount;
    private ?\SplObjectStorage $inlinedDefinitions = null;
    private ?array $serviceCalls = null;
    private array $reservedVariables = ['instance', 'class', 'this', 'container'];
    private ExpressionLanguage $expressionLanguage;
    private ?string $targetDirRegex = null;
    private int $targetDirMaxMatches;
    private string $docStar;
    private array $serviceIdToMethodNameMap;
    private array $usedMethodNames;
    private string $namespace;
    private bool $asFiles;
    private string $hotPathTag;
    private array $preloadTags;
    private bool $inlineFactories;
    private bool $inlineRequires;
    private array $inlinedRequires = [];
    private array $circularReferences = [];
    private array $singleUsePrivateIds = [];
    private array $preload = [];
    private bool $addGetService = false;
    private array $locatedIds = [];
    private string $serviceLocatorTag;
    private array $exportedVariables = [];
    private array $dynamicParameters = [];
    private string $baseClass;
    private string $class;
    private DumperInterface $proxyDumper;
    private bool $hasProxyDumper = true;

    public function __construct(ContainerBuilder $container)
    {
        if (!$container->isCompiled()) {
            throw new LogicException('Cannot dump an uncompiled container.');
        }

        parent::__construct($container);
    }

    /**
     * Sets the dumper to be used when dumping proxies in the generated container.
     */
    public function setProxyDumper(DumperInterface $proxyDumper): void
    {
        $this->proxyDumper = $proxyDumper;
        $this->hasProxyDumper = !$proxyDumper instanceof NullDumper;
    }

    /**
     * Dumps the service container as a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *  * namespace:  The class namespace
     *  * as_files:   To split the container in several files
     *
     * @return string|array A PHP class representing the service container or an array of PHP files if the "as_files" option is set
     *
     * @throws EnvParameterException When an env var exists but has not been dumped
     */
    public function dump(array $options = []): string|array
    {
        $this->locatedIds = [];
        $this->targetDirRegex = null;
        $this->inlinedRequires = [];
        $this->exportedVariables = [];
        $this->dynamicParameters = [];
        $options = array_merge([
            'class' => 'ProjectServiceContainer',
            'base_class' => 'Container',
            'namespace' => '',
            'as_files' => false,
            'debug' => true,
            'hot_path_tag' => 'container.hot_path',
            'preload_tags' => ['container.preload', 'container.no_preload'],
            'inline_factories' => null,
            'inline_class_loader' => null,
            'preload_classes' => [],
            'service_locator_tag' => 'container.service_locator',
            'build_time' => time(),
        ], $options);

        $this->addGetService = false;
        $this->namespace = $options['namespace'];
        $this->asFiles = $options['as_files'];
        $this->hotPathTag = $options['hot_path_tag'];
        $this->preloadTags = $options['preload_tags'];

        $this->inlineFactories = false;
        if (isset($options['inline_factories'])) {
            $this->inlineFactories = $this->asFiles && $options['inline_factories'];
        }

        $this->inlineRequires = $options['debug'];
        if (isset($options['inline_class_loader'])) {
            $this->inlineRequires = $options['inline_class_loader'];
        }

        $this->serviceLocatorTag = $options['service_locator_tag'];
        $this->class = $options['class'];

        if (!str_starts_with($baseClass = $options['base_class'], '\\') && 'Container' !== $baseClass) {
            $baseClass = \sprintf('%s\%s', $options['namespace'] ? '\\'.$options['namespace'] : '', $baseClass);
            $this->baseClass = $baseClass;
        } elseif ('Container' === $baseClass) {
            $this->baseClass = Container::class;
        } else {
            $this->baseClass = $baseClass;
        }

        $this->initializeMethodNamesMap('Container' === $baseClass ? Container::class : $baseClass);

        if (!$this->hasProxyDumper) {
            (new AnalyzeServiceReferencesPass(true, false))->process($this->container);
            (new CheckCircularReferencesPass())->process($this->container);
        }

        $this->analyzeReferences();
        $this->docStar = $options['debug'] ? '*' : '';

        if (!empty($options['file']) && is_dir($dir = \dirname($options['file']))) {
            // Build a regexp where the first root dirs are mandatory,
            // but every other sub-dir is optional up to the full path in $dir
            // Mandate at least 1 root dir and not more than 5 optional dirs.

            $dir = explode(\DIRECTORY_SEPARATOR, realpath($dir));
            $i = \count($dir);

            if (2 + (int) ('\\' === \DIRECTORY_SEPARATOR) <= $i) {
                $regex = '';
                $lastOptionalDir = $i > 8 ? $i - 5 : (2 + (int) ('\\' === \DIRECTORY_SEPARATOR));
                $this->targetDirMaxMatches = $i - $lastOptionalDir;

                while (--$i >= $lastOptionalDir) {
                    $regex = \sprintf('(%s%s)?', preg_quote(\DIRECTORY_SEPARATOR.$dir[$i], '#'), $regex);
                }

                do {
                    $regex = preg_quote(\DIRECTORY_SEPARATOR.$dir[$i], '#').$regex;
                } while (0 < --$i);

                $this->targetDirRegex = '#(^|file://|[:;, \|\r\n])'.preg_quote($dir[0], '#').$regex.'#';
            }
        }

        $proxyClasses = $this->inlineFactories ? $this->generateProxyClasses() : null;

        if ($options['preload_classes']) {
            $this->preload = array_combine($options['preload_classes'], $options['preload_classes']);
        }

        $code = $this->addDefaultParametersMethod();
        $code =
            $this->startClass($options['class'], $baseClass, $this->inlineFactories && $proxyClasses).
            $this->addServices($services).
            $this->addDeprecatedAliases().
            $code
        ;

        $proxyClasses ??= $this->generateProxyClasses();

        if ($this->addGetService) {
            $code = preg_replace(
                "/\r?\n\r?\n    public function __construct.+?\\{\r?\n/s",
                "\n    protected \Closure \$getService;$0",
                $code,
                1
            );
        }

        if ($this->asFiles) {
            $fileTemplate = <<<EOF
<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/*{$this->docStar}
 * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
 */
class %s extends {$options['class']}
{%s}

EOF;
            $files = [];
            $preloadedFiles = [];
            $ids = $this->container->getRemovedIds();
            foreach ($this->container->getDefinitions() as $id => $definition) {
                if (!$definition->isPublic() && '.' !== ($id[0] ?? '-')) {
                    $ids[$id] = true;
                }
            }
            if ($ids = array_keys($ids)) {
                sort($ids);
                $c = "<?php\n\nreturn [\n";
                foreach ($ids as $id) {
                    $c .= '    '.$this->doExport($id)." => true,\n";
                }
                $files['removed-ids.php'] = $c."];\n";
            }

            if (!$this->inlineFactories) {
                foreach ($this->generateServiceFiles($services) as $file => [$c, $preload]) {
                    $files[$file] = \sprintf($fileTemplate, substr($file, 0, -4), $c);

                    if ($preload) {
                        $preloadedFiles[$file] = $file;
                    }
                }
                foreach ($proxyClasses as $file => $c) {
                    $files[$file] = "<?php\n".$c;
                    $preloadedFiles[$file] = $file;
                }
            }

            $code .= $this->endClass();

            if ($this->inlineFactories && $proxyClasses) {
                $files['proxy-classes.php'] = "<?php\n\n";

                foreach ($proxyClasses as $c) {
                    $files['proxy-classes.php'] .= $c;
                }
            }

            $files[$options['class'].'.php'] = $code;
            $hash = ucfirst(strtr(ContainerBuilder::hash($files), '._', 'xx'));
            $code = [];

            foreach ($files as $file => $c) {
                $code["Container{$hash}/{$file}"] = substr_replace($c, "<?php\n\nnamespace Container{$hash};\n", 0, 6);

                if (isset($preloadedFiles[$file])) {
                    $preloadedFiles[$file] = "Container{$hash}/{$file}";
                }
            }
            $namespaceLine = $this->namespace ? "\nnamespace {$this->namespace};\n" : '';
            $time = $options['build_time'];
            $id = hash('crc32', $hash.$time);
            $this->asFiles = false;

            if ($this->preload && null !== $autoloadFile = $this->getAutoloadFile()) {
                $autoloadFile = trim($this->export($autoloadFile), '()\\');

                $preloadedFiles = array_reverse($preloadedFiles);
                if ('' !== $preloadedFiles = implode("';\nrequire __DIR__.'/", $preloadedFiles)) {
                    $preloadedFiles = "require __DIR__.'/$preloadedFiles';\n";
                }

                $code[$options['class'].'.preload.php'] = <<<EOF
<?php

// This file has been auto-generated by the Symfony Dependency Injection Component
// You can reference it in the "opcache.preload" php.ini setting on PHP >= 7.4 when preloading is desired

use Symfony\Component\DependencyInjection\Dumper\Preloader;

if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    return;
}

require $autoloadFile;
(require __DIR__.'/{$options['class']}.php')->set(\\Container{$hash}\\{$options['class']}::class, null);
$preloadedFiles
\$classes = [];

EOF;

                foreach ($this->preload as $class) {
                    if (!$class || str_contains($class, '$') || \in_array($class, ['int', 'float', 'string', 'bool', 'resource', 'object', 'array', 'null', 'callable', 'iterable', 'mixed', 'void'], true)) {
                        continue;
                    }
                    if (!(class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) || (new \ReflectionClass($class))->isUserDefined()) {
                        $code[$options['class'].'.preload.php'] .= \sprintf("\$classes[] = '%s';\n", $class);
                    }
                }

                $code[$options['class'].'.preload.php'] .= <<<'EOF'

$preloaded = Preloader::preload($classes);

EOF;
            }

            $code[$options['class'].'.php'] = <<<EOF
<?php
{$namespaceLine}
// This file has been auto-generated by the Symfony Dependency Injection Component for internal use.

if (\\class_exists(\\Container{$hash}\\{$options['class']}::class, false)) {
    // no-op
} elseif (!include __DIR__.'/Container{$hash}/{$options['class']}.php') {
    touch(__DIR__.'/Container{$hash}.legacy');

    return;
}

if (!\\class_exists({$options['class']}::class, false)) {
    \\class_alias(\\Container{$hash}\\{$options['class']}::class, {$options['class']}::class, false);
}

return new \\Container{$hash}\\{$options['class']}([
    'container.build_hash' => '$hash',
    'container.build_id' => '$id',
    'container.build_time' => $time,
    'container.runtime_mode' => \\in_array(\\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) ? 'web=0' : 'web=1',
], __DIR__.\\DIRECTORY_SEPARATOR.'Container{$hash}');

EOF;
        } else {
            $code .= $this->endClass();
            foreach ($proxyClasses as $c) {
                $code .= $c;
            }
        }

        $this->targetDirRegex = null;
        $this->inlinedRequires = [];
        $this->circularReferences = [];
        $this->locatedIds = [];
        $this->exportedVariables = [];
        $this->dynamicParameters = [];
        $this->preload = [];

        $unusedEnvs = [];
        foreach ($this->container->getEnvCounters() as $env => $use) {
            if (!$use) {
                $unusedEnvs[] = $env;
            }
        }
        if ($unusedEnvs) {
            throw new EnvParameterException($unusedEnvs, null, 'Environment variables "%s" are never used. Please, check your container\'s configuration.');
        }

        return $code;
    }

    /**
     * Retrieves the currently set proxy dumper or instantiates one.
     */
    private function getProxyDumper(): DumperInterface
    {
        return $this->proxyDumper ??= new LazyServiceDumper($this->class);
    }

    private function analyzeReferences(): void
    {
        (new AnalyzeServiceReferencesPass(false, $this->hasProxyDumper))->process($this->container);
        $checkedNodes = [];
        $this->circularReferences = [];
        $this->singleUsePrivateIds = [];
        foreach ($this->container->getCompiler()->getServiceReferenceGraph()->getNodes() as $id => $node) {
            if (!$node->getValue() instanceof Definition) {
                continue;
            }

            if ($this->isSingleUsePrivateNode($node)) {
                $this->singleUsePrivateIds[$id] = $id;
            }

            $this->collectCircularReferences($id, $node->getOutEdges(), $checkedNodes);
        }

        $this->container->getCompiler()->getServiceReferenceGraph()->clear();
        $this->singleUsePrivateIds = array_diff_key($this->singleUsePrivateIds, $this->circularReferences);
    }

    private function collectCircularReferences(string $sourceId, array $edges, array &$checkedNodes, array &$loops = [], array $path = [], bool $byConstructor = true): void
    {
        $path[$sourceId] = $byConstructor;
        $checkedNodes[$sourceId] = true;
        foreach ($edges as $edge) {
            $node = $edge->getDestNode();
            $id = $node->getId();
            if ($sourceId === $id || !$node->getValue() instanceof Definition || $edge->isWeak()) {
                continue;
            }

            if (isset($path[$id])) {
                $loop = null;
                $loopByConstructor = $edge->isReferencedByConstructor() && !$edge->isLazy();
                $pathInLoop = [$id, []];
                foreach ($path as $k => $pathByConstructor) {
                    if (null !== $loop) {
                        $loop[] = $k;
                        $pathInLoop[1][$k] = $pathByConstructor;
                        $loops[$k][] = &$pathInLoop;
                        $loopByConstructor = $loopByConstructor && $pathByConstructor;
                    } elseif ($k === $id) {
                        $loop = [];
                    }
                }
                $this->addCircularReferences($id, $loop, $loopByConstructor);
            } elseif (!isset($checkedNodes[$id])) {
                $this->collectCircularReferences($id, $node->getOutEdges(), $checkedNodes, $loops, $path, $edge->isReferencedByConstructor() && !$edge->isLazy());
            } elseif (isset($loops[$id])) {
                // we already had detected loops for this edge
                // let's check if we have a common ancestor in one of the detected loops
                foreach ($loops[$id] as [$first, $loopPath]) {
                    if (!isset($path[$first])) {
                        continue;
                    }
                    // We have a common ancestor, let's fill the current path
                    $fillPath = null;
                    foreach ($loopPath as $k => $pathByConstructor) {
                        if (null !== $fillPath) {
                            $fillPath[$k] = $pathByConstructor;
                        } elseif ($k === $id) {
                            $fillPath = $path;
                            $fillPath[$k] = $pathByConstructor;
                        }
                    }

                    // we can now build the loop
                    $loop = null;
                    $loopByConstructor = $edge->isReferencedByConstructor() && !$edge->isLazy();
                    foreach ($fillPath as $k => $pathByConstructor) {
                        if (null !== $loop) {
                            $loop[] = $k;
                            $loopByConstructor = $loopByConstructor && $pathByConstructor;
                        } elseif ($k === $first) {
                            $loop = [];
                        }
                    }
                    $this->addCircularReferences($first, $loop, $loopByConstructor);
                    break;
                }
            }
        }
        unset($path[$sourceId]);
    }

    private function addCircularReferences(string $sourceId, array $currentPath, bool $byConstructor): void
    {
        $currentId = $sourceId;
        $currentPath = array_reverse($currentPath);
        $currentPath[] = $currentId;
        foreach ($currentPath as $parentId) {
            if (empty($this->circularReferences[$parentId][$currentId])) {
                $this->circularReferences[$parentId][$currentId] = $byConstructor;
            }

            $currentId = $parentId;
        }
    }

    private function collectLineage(string $class, array &$lineage): void
    {
        if (isset($lineage[$class])) {
            return;
        }
        if (!$r = $this->container->getReflectionClass($class, false)) {
            return;
        }
        if (is_a($class, $this->baseClass, true)) {
            return;
        }
        $file = $r->getFileName();
        if (str_ends_with($file, ') : eval()\'d code')) {
            $file = substr($file, 0, strrpos($file, '(', -17));
        }
        if (!$file || $this->doExport($file) === $exportedFile = $this->export($file)) {
            return;
        }

        $lineage[$class] = substr($exportedFile, 1, -1);

        if ($parent = $r->getParentClass()) {
            $this->collectLineage($parent->name, $lineage);
        }

        foreach ($r->getInterfaces() as $parent) {
            $this->collectLineage($parent->name, $lineage);
        }

        foreach ($r->getTraits() as $parent) {
            $this->collectLineage($parent->name, $lineage);
        }

        unset($lineage[$class]);
        $lineage[$class] = substr($exportedFile, 1, -1);
    }

    private function generateProxyClasses(): array
    {
        $proxyClasses = [];
        $alreadyGenerated = [];
        $definitions = $this->container->getDefinitions();
        $strip = '' === $this->docStar;
        $proxyDumper = $this->getProxyDumper();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition = $this->isProxyCandidate($definition, $asGhostObject, $id)) {
                continue;
            }
            if (isset($alreadyGenerated[$asGhostObject][$class = $definition->getClass()])) {
                continue;
            }
            $alreadyGenerated[$asGhostObject][$class] = true;

            foreach (array_column($definition->getTag('proxy'), 'interface') ?: [$class] as $r) {
                if (!$r = $this->container->getReflectionClass($r)) {
                    continue;
                }
                do {
                    $file = $r->getFileName();
                    if (str_ends_with($file, ') : eval()\'d code')) {
                        $file = substr($file, 0, strrpos($file, '(', -17));
                    }
                    if (is_file($file)) {
                        $this->container->addResource(new FileResource($file));
                    }
                    $r = $r->getParentClass() ?: null;
                } while ($r?->isUserDefined());
            }

            if ("\n" === $proxyCode = "\n".$proxyDumper->getProxyCode($definition, $id)) {
                continue;
            }

            if ($this->inlineRequires) {
                $lineage = [];
                $this->collectLineage($class, $lineage);

                $code = '';
                foreach (array_diff_key(array_flip($lineage), $this->inlinedRequires) as $file => $class) {
                    if ($this->inlineFactories) {
                        $this->inlinedRequires[$file] = true;
                    }
                    $code .= \sprintf("include_once %s;\n", $file);
                }

                $proxyCode = $code.$proxyCode;
            }

            if ($strip) {
                $proxyCode = "<?php\n".$proxyCode;
                $proxyCode = substr(self::stripComments($proxyCode), 5);
            }

            $proxyClass = $this->inlineRequires ? substr($proxyCode, \strlen($code)) : $proxyCode;
            $i = strpos($proxyClass, 'class');
            $proxyClass = substr($proxyClass, 6 + $i, strpos($proxyClass, ' ', 7 + $i) - $i - 6);

            if ($this->asFiles || $this->namespace) {
                $proxyCode .= "\nif (!\\class_exists('$proxyClass', false)) {\n    \\class_alias(__NAMESPACE__.'\\\\$proxyClass', '$proxyClass', false);\n}\n";
            }

            $proxyClasses[$proxyClass.'.php'] = $proxyCode;
        }

        return $proxyClasses;
    }

    private function addServiceInclude(string $cId, Definition $definition, bool $isProxyCandidate): string
    {
        $code = '';

        if ($this->inlineRequires && (!$this->isHotPath($definition) || $isProxyCandidate)) {
            $lineage = [];
            foreach ($this->inlinedDefinitions as $def) {
                if (!$def->isDeprecated()) {
                    foreach ($this->getClasses($def, $cId) as $class) {
                        $this->collectLineage($class, $lineage);
                    }
                }
            }

            foreach ($this->serviceCalls as $id => [$callCount, $behavior]) {
                if ('service_container' !== $id && $id !== $cId
                    && ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE !== $behavior
                    && $this->container->has($id)
                    && $this->isTrivialInstance($def = $this->container->findDefinition($id))
                ) {
                    foreach ($this->getClasses($def, $cId) as $class) {
                        $this->collectLineage($class, $lineage);
                    }
                }
            }

            foreach (array_diff_key(array_flip($lineage), $this->inlinedRequires) as $file => $class) {
                $code .= \sprintf("        include_once %s;\n", $file);
            }
        }

        foreach ($this->inlinedDefinitions as $def) {
            if ($file = $def->getFile()) {
                $file = $this->dumpValue($file);
                $file = '(' === $file[0] ? substr($file, 1, -1) : $file;
                $code .= \sprintf("        include_once %s;\n", $file);
            }
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function addServiceInstance(string $id, Definition $definition, bool $isSimpleInstance): string
    {
        $class = $this->dumpValue($definition->getClass());

        if (str_starts_with($class, "'") && !str_contains($class, '$') && !preg_match('/^\'(?:\\\{2})?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid class name for the "%s" service.', $class, $id));
        }

        $asGhostObject = false;
        $isProxyCandidate = $this->isProxyCandidate($definition, $asGhostObject, $id);
        $instantiation = '';

        $lastWitherIndex = null;
        foreach ($definition->getMethodCalls() as $k => $call) {
            if ($call[2] ?? false) {
                $lastWitherIndex = $k;
            }
        }

        if (!$isProxyCandidate && $definition->isShared() && !isset($this->singleUsePrivateIds[$id]) && null === $lastWitherIndex) {
            $instantiation = \sprintf('$container->%s[%s] = %s', $this->container->getDefinition($id)->isPublic() ? 'services' : 'privates', $this->doExport($id), $isSimpleInstance ? '' : '$instance');
        } elseif (!$isSimpleInstance) {
            $instantiation = '$instance';
        }

        $return = '';
        if ($isSimpleInstance) {
            $return = 'return ';
        } else {
            $instantiation .= ' = ';
        }

        return $this->addNewInstance($definition, '        '.$return.$instantiation, $id, $asGhostObject);
    }

    private function isTrivialInstance(Definition $definition): bool
    {
        if ($definition->hasErrors()) {
            return true;
        }
        if ($definition->isSynthetic() || $definition->getFile() || $definition->getMethodCalls() || $definition->getProperties() || $definition->getConfigurator()) {
            return false;
        }
        if ($definition->isDeprecated() || $definition->isLazy() || $definition->getFactory() || 3 < \count($definition->getArguments())) {
            return false;
        }

        foreach ($definition->getArguments() as $arg) {
            if (!$arg || $arg instanceof Parameter) {
                continue;
            }
            if (\is_array($arg) && 3 >= \count($arg)) {
                foreach ($arg as $k => $v) {
                    if ($this->dumpValue($k) !== $this->dumpValue($k, false)) {
                        return false;
                    }
                    if (!$v || $v instanceof Parameter) {
                        continue;
                    }
                    if ($v instanceof Reference && $this->container->has($id = (string) $v) && $this->container->findDefinition($id)->isSynthetic()) {
                        continue;
                    }
                    if (!\is_scalar($v) || $this->dumpValue($v) !== $this->dumpValue($v, false)) {
                        return false;
                    }
                }
            } elseif ($arg instanceof Reference && $this->container->has($id = (string) $arg) && $this->container->findDefinition($id)->isSynthetic()) {
                continue;
            } elseif (!\is_scalar($arg) || $this->dumpValue($arg) !== $this->dumpValue($arg, false)) {
                return false;
            }
        }

        return true;
    }

    private function addServiceMethodCalls(Definition $definition, string $variableName, ?string $sharedNonLazyId): string
    {
        $lastWitherIndex = null;
        foreach ($definition->getMethodCalls() as $k => $call) {
            if ($call[2] ?? false) {
                $lastWitherIndex = $k;
            }
        }

        $calls = '';
        foreach ($definition->getMethodCalls() as $k => $call) {
            $arguments = [];
            foreach ($call[1] as $i => $value) {
                $arguments[] = (\is_string($i) ? $i.': ' : '').$this->dumpValue($value);
            }

            $witherAssignation = '';

            if ($call[2] ?? false) {
                if (null !== $sharedNonLazyId && $lastWitherIndex === $k && 'instance' === $variableName) {
                    $witherAssignation = \sprintf('$container->%s[\'%s\'] = ', $definition->isPublic() ? 'services' : 'privates', $sharedNonLazyId);
                }
                $witherAssignation .= \sprintf('$%s = ', $variableName);
            }

            $calls .= $this->wrapServiceConditionals($call[1], \sprintf("        %s\$%s->%s(%s);\n", $witherAssignation, $variableName, $call[0], implode(', ', $arguments)));
        }

        return $calls;
    }

    private function addServiceProperties(Definition $definition, string $variableName = 'instance'): string
    {
        $code = '';
        foreach ($definition->getProperties() as $name => $value) {
            $code .= \sprintf("        \$%s->%s = %s;\n", $variableName, $name, $this->dumpValue($value));
        }

        return $code;
    }

    private function addServiceConfigurator(Definition $definition, string $variableName = 'instance'): string
    {
        if (!$callable = $definition->getConfigurator()) {
            return '';
        }

        if (\is_array($callable)) {
            if ($callable[0] instanceof Reference
                || ($callable[0] instanceof Definition && $this->definitionVariables->contains($callable[0]))
            ) {
                return \sprintf("        %s->%s(\$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
            }

            $class = $this->dumpValue($callable[0]);
            // If the class is a string we can optimize away
            if (str_starts_with($class, "'") && !str_contains($class, '$')) {
                return \sprintf("        %s::%s(\$%s);\n", $this->dumpLiteralClass($class), $callable[1], $variableName);
            }

            if (str_starts_with($class, 'new ')) {
                return \sprintf("        (%s)->%s(\$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
            }

            return \sprintf("        [%s, '%s'](\$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
        }

        return \sprintf("        %s(\$%s);\n", $callable, $variableName);
    }

    private function addService(string $id, Definition $definition): array
    {
        $this->definitionVariables = new \SplObjectStorage();
        $this->referenceVariables = [];
        $this->variableCount = 0;
        $this->referenceVariables[$id] = new Variable('instance');

        $return = [];

        if ($class = $definition->getClass()) {
            $class = $class instanceof Parameter ? '%'.$class.'%' : $this->container->resolveEnvPlaceholders($class);
            $return[] = \sprintf(str_starts_with($class, '%') ? '@return object A %1$s instance' : '@return \%s', ltrim($class, '\\'));
        } elseif ($definition->getFactory()) {
            $factory = $definition->getFactory();
            if (\is_string($factory) && !str_starts_with($factory, '@=')) {
                $return[] = \sprintf('@return object An instance returned by %s()', $factory);
            } elseif (\is_array($factory) && (\is_string($factory[0]) || $factory[0] instanceof Definition || $factory[0] instanceof Reference)) {
                $class = $factory[0] instanceof Definition ? $factory[0]->getClass() : (string) $factory[0];
                $class = $class instanceof Parameter ? '%'.$class.'%' : $this->container->resolveEnvPlaceholders($class);
                $return[] = \sprintf('@return object An instance returned by %s::%s()', $class, $factory[1]);
            }
        }

        if ($definition->isDeprecated()) {
            if ($return && str_starts_with($return[\count($return) - 1], '@return')) {
                $return[] = '';
            }

            $deprecation = $definition->getDeprecation($id);
            $return[] = \sprintf('@deprecated %s', ($deprecation['package'] || $deprecation['version'] ? "Since {$deprecation['package']} {$deprecation['version']}: " : '').$deprecation['message']);
        }

        $return = str_replace("\n     * \n", "\n     *\n", implode("\n     * ", $return));
        $return = $this->container->resolveEnvPlaceholders($return);

        $shared = $definition->isShared() ? ' shared' : '';
        $public = $definition->isPublic() ? 'public' : 'private';
        $autowired = $definition->isAutowired() ? ' autowired' : '';
        $asFile = $this->asFiles && !$this->inlineFactories && !$this->isHotPath($definition);
        $methodName = $this->generateMethodName($id);

        if ($asFile || $definition->isLazy()) {
            $lazyInitialization = ', $lazyLoad = true';
        } else {
            $lazyInitialization = '';
        }

        $code = <<<EOF

    /*{$this->docStar}
     * Gets the $public '$id'$shared$autowired service.
     *
     * $return
EOF;
        $code = str_replace('*/', ' ', $code).<<<EOF

     */
    protected static function {$methodName}(\$container$lazyInitialization)
    {

EOF;

        if ($asFile) {
            $file = $methodName.'.php';
            $code = str_replace("protected static function {$methodName}(", 'public static function do(', $code);
        } else {
            $file = null;
        }

        if ($definition->hasErrors() && $e = $definition->getErrors()) {
            $code .= \sprintf("        throw new RuntimeException(%s);\n", $this->export(reset($e)));
        } else {
            $this->serviceCalls = [];
            $this->inlinedDefinitions = $this->getDefinitionsFromArguments([$definition], null, $this->serviceCalls);

            if ($definition->isDeprecated()) {
                $deprecation = $definition->getDeprecation($id);
                $code .= \sprintf("        trigger_deprecation(%s, %s, %s);\n\n", $this->export($deprecation['package']), $this->export($deprecation['version']), $this->export($deprecation['message']));
            } elseif ($definition->hasTag($this->hotPathTag) || !$definition->hasTag($this->preloadTags[1])) {
                foreach ($this->inlinedDefinitions as $def) {
                    foreach ($this->getClasses($def, $id) as $class) {
                        $this->preload[$class] = $class;
                    }
                }
            }

            if (!$definition->isShared()) {
                $factory = \sprintf('$container->factories%s[%s]', $definition->isPublic() ? '' : "['service_container']", $this->doExport($id));
            }

            $asGhostObject = false;
            if ($isProxyCandidate = $this->isProxyCandidate($definition, $asGhostObject, $id)) {
                $definition = $isProxyCandidate;

                if (!$definition->isShared()) {
                    $code .= \sprintf('        %s ??= ', $factory);

                    if ($definition->isPublic()) {
                        $code .= \sprintf("fn () => self::%s(\$container);\n\n", $asFile ? 'do' : $methodName);
                    } else {
                        $code .= \sprintf("self::%s(...);\n\n", $asFile ? 'do' : $methodName);
                    }
                }
                $lazyLoad = $asGhostObject ? '$proxy' : 'false';

                $factoryCode = $asFile ? \sprintf('self::do($container, %s)', $lazyLoad) : \sprintf('self::%s($container, %s)', $methodName, $lazyLoad);
                $code .= $this->getProxyDumper()->getProxyFactoryCode($definition, $id, $factoryCode);
            }

            $c = $this->addServiceInclude($id, $definition, null !== $isProxyCandidate);

            if ('' !== $c && $isProxyCandidate && !$definition->isShared()) {
                $c = implode("\n", array_map(fn ($line) => $line ? '    '.$line : $line, explode("\n", $c)));
                $code .= "        static \$include = true;\n\n";
                $code .= "        if (\$include) {\n";
                $code .= $c;
                $code .= "            \$include = false;\n";
                $code .= "        }\n\n";
            } else {
                $code .= $c;
            }

            $c = $this->addInlineService($id, $definition);

            if (!$isProxyCandidate && !$definition->isShared()) {
                $c = implode("\n", array_map(fn ($line) => $line ? '    '.$line : $line, explode("\n", $c)));
                $lazyloadInitialization = $definition->isLazy() ? ', $lazyLoad = true' : '';

                $c = \sprintf("        %s = function (\$container%s) {\n%s        };\n\n        return %1\$s(\$container);\n", $factory, $lazyloadInitialization, $c);
            }

            $code .= $c;
        }

        $code .= "    }\n";

        $this->definitionVariables = $this->inlinedDefinitions = null;
        $this->referenceVariables = $this->serviceCalls = null;

        return [$file, $code];
    }

    private function addInlineVariables(string $id, Definition $definition, array $arguments, bool $forConstructor): string
    {
        $code = '';

        foreach ($arguments as $argument) {
            if (\is_array($argument)) {
                $code .= $this->addInlineVariables($id, $definition, $argument, $forConstructor);
            } elseif ($argument instanceof Reference) {
                $code .= $this->addInlineReference($id, $definition, $argument, $forConstructor);
            } elseif ($argument instanceof Definition) {
                $code .= $this->addInlineService($id, $definition, $argument, $forConstructor);
            }
        }

        return $code;
    }

    private function addInlineReference(string $id, Definition $definition, string $targetId, bool $forConstructor): string
    {
        while ($this->container->hasAlias($targetId)) {
            $targetId = (string) $this->container->getAlias($targetId);
        }

        [$callCount, $behavior] = $this->serviceCalls[$targetId];

        if ($id === $targetId) {
            return $this->addInlineService($id, $definition, $definition);
        }

        if ('service_container' === $targetId || isset($this->referenceVariables[$targetId])) {
            return '';
        }

        if ($this->container->hasDefinition($targetId) && ($def = $this->container->getDefinition($targetId)) && !$def->isShared()) {
            return '';
        }

        $hasSelfRef = isset($this->circularReferences[$id][$targetId]) && !isset($this->definitionVariables[$definition]) && !($this->hasProxyDumper && $definition->isLazy());

        if ($hasSelfRef && !$forConstructor && !$forConstructor = !$this->circularReferences[$id][$targetId]) {
            $code = $this->addInlineService($id, $definition, $definition);
        } else {
            $code = '';
        }

        if (isset($this->referenceVariables[$targetId]) || (2 > $callCount && (!$hasSelfRef || !$forConstructor))) {
            return $code;
        }

        $name = $this->getNextVariableName();
        $this->referenceVariables[$targetId] = new Variable($name);

        $reference = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE >= $behavior ? new Reference($targetId, $behavior) : null;
        $code .= \sprintf("        \$%s = %s;\n", $name, $this->getServiceCall($targetId, $reference));

        if (!$hasSelfRef || !$forConstructor) {
            return $code;
        }

        return $code.\sprintf(<<<'EOTXT'

        if (isset($container->%s[%s])) {
            return $container->%1$s[%2$s];
        }

EOTXT
            ,
            $this->container->getDefinition($id)->isPublic() ? 'services' : 'privates',
            $this->doExport($id)
        );
    }

    private function addInlineService(string $id, Definition $definition, ?Definition $inlineDef = null, bool $forConstructor = true): string
    {
        $code = '';

        if ($isSimpleInstance = $isRootInstance = null === $inlineDef) {
            foreach ($this->serviceCalls as $targetId => [$callCount, $behavior, $byConstructor]) {
                if ($byConstructor && isset($this->circularReferences[$id][$targetId]) && !$this->circularReferences[$id][$targetId] && !($this->hasProxyDumper && $definition->isLazy())) {
                    $code .= $this->addInlineReference($id, $definition, $targetId, $forConstructor);
                }
            }
        }

        if (isset($this->definitionVariables[$inlineDef ??= $definition])) {
            return $code;
        }

        $arguments = [$inlineDef->getArguments(), $inlineDef->getFactory()];

        $code .= $this->addInlineVariables($id, $definition, $arguments, $forConstructor);

        if ($arguments = array_filter([$inlineDef->getProperties(), $inlineDef->getMethodCalls(), $inlineDef->getConfigurator()])) {
            $isSimpleInstance = false;
        } elseif ($definition !== $inlineDef && 2 > $this->inlinedDefinitions[$inlineDef]) {
            return $code;
        }

        $asGhostObject = false;
        $isProxyCandidate = $this->isProxyCandidate($inlineDef, $asGhostObject, $id);

        if (isset($this->definitionVariables[$inlineDef])) {
            $isSimpleInstance = false;
        } else {
            $name = $definition === $inlineDef ? 'instance' : $this->getNextVariableName();
            $this->definitionVariables[$inlineDef] = new Variable($name);
            $code .= '' !== $code ? "\n" : '';

            if ('instance' === $name) {
                $code .= $this->addServiceInstance($id, $definition, $isSimpleInstance);
            } else {
                $code .= $this->addNewInstance($inlineDef, '        $'.$name.' = ', $id);
            }

            if ('' !== $inline = $this->addInlineVariables($id, $definition, $arguments, false)) {
                $code .= "\n".$inline."\n";
            } elseif ($arguments && 'instance' === $name) {
                $code .= "\n";
            }

            $code .= $this->addServiceProperties($inlineDef, $name);
            $code .= $this->addServiceMethodCalls($inlineDef, $name, !$isProxyCandidate && $inlineDef->isShared() && !isset($this->singleUsePrivateIds[$id]) ? $id : null);
            $code .= $this->addServiceConfigurator($inlineDef, $name);
        }

        if (!$isRootInstance || $isSimpleInstance) {
            return $code;
        }

        return $code."\n        return \$instance;\n";
    }

    private function addServices(?array &$services = null): string
    {
        $publicServices = $privateServices = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->isSynthetic()) {
                $services[$id] = $this->addService($id, $definition);
            } elseif ($definition->hasTag($this->hotPathTag) || !$definition->hasTag($this->preloadTags[1])) {
                $services[$id] = null;

                foreach ($this->getClasses($definition, $id) as $class) {
                    $this->preload[$class] = $class;
                }
            }
        }

        foreach ($definitions as $id => $definition) {
            if (!([$file, $code] = $services[$id]) || null !== $file) {
                continue;
            }
            if ($definition->isPublic()) {
                $publicServices .= $code;
            } elseif (!$this->isTrivialInstance($definition) || isset($this->locatedIds[$id])) {
                $privateServices .= $code;
            }
        }

        return $publicServices.$privateServices;
    }

    private function generateServiceFiles(array $services): iterable
    {
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (([$file, $code] = $services[$id]) && null !== $file && ($definition->isPublic() || !$this->isTrivialInstance($definition) || isset($this->locatedIds[$id]))) {
                yield $file => [$code, $definition->hasTag($this->hotPathTag) || !$definition->hasTag($this->preloadTags[1]) && !$definition->isDeprecated() && !$definition->hasErrors()];
            }
        }
    }

    private function addNewInstance(Definition $definition, string $return = '', ?string $id = null, bool $asGhostObject = false): string
    {
        $tail = $return ? str_repeat(')', substr_count($return, '(') - substr_count($return, ')')).";\n" : '';

        $arguments = [];
        if (BaseServiceLocator::class === $definition->getClass() && $definition->hasTag($this->serviceLocatorTag)) {
            foreach ($definition->getArgument(0) as $k => $argument) {
                $arguments[$k] = $argument->getValues()[0];
            }

            return $return.$this->dumpValue(new ServiceLocatorArgument($arguments)).$tail;
        }

        foreach ($definition->getArguments() as $i => $value) {
            $arguments[] = (\is_string($i) ? $i.': ' : '').$this->dumpValue($value);
        }

        if (null !== $definition->getFactory()) {
            $callable = $definition->getFactory();

            if ('current' === $callable && [0] === array_keys($definition->getArguments()) && \is_array($value) && [0] === array_keys($value)) {
                return $return.$this->dumpValue($value[0]).$tail;
            }

            if (['Closure', 'fromCallable'] === $callable) {
                $callable = $definition->getArgument(0);
                if ($callable instanceof ServiceClosureArgument) {
                    return $return.$this->dumpValue($callable).$tail;
                }

                $arguments = ['...'];

                if ($callable instanceof Reference || $callable instanceof Definition) {
                    $callable = [$callable, '__invoke'];
                }
            }

            if (\is_string($callable) && str_starts_with($callable, '@=')) {
                return $return.\sprintf('(($args = %s) ? (%s) : null)',
                    $this->dumpValue(new ServiceLocatorArgument($definition->getArguments())),
                    $this->getExpressionLanguage()->compile(substr($callable, 2), ['container' => 'container', 'args' => 'args'])
                ).$tail;
            }

            if (!\is_array($callable)) {
                return $return.\sprintf('%s(%s)', $this->dumpLiteralClass($this->dumpValue($callable)), $arguments ? implode(', ', $arguments) : '').$tail;
            }

            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $callable[1])) {
                throw new RuntimeException(\sprintf('Cannot dump definition because of invalid factory method (%s).', $callable[1] ?: 'n/a'));
            }

            if (['...'] === $arguments && ($definition->isLazy() || 'Closure' !== ($definition->getClass() ?? 'Closure')) && (
                $callable[0] instanceof Reference
                || ($callable[0] instanceof Definition && !$this->definitionVariables->contains($callable[0]))
            )) {
                $initializer = 'fn () => '.$this->dumpValue($callable[0]);

                return $return.LazyClosure::getCode($initializer, $callable, $definition, $this->container, $id).$tail;
            }

            if ($callable[0] instanceof Reference
                || ($callable[0] instanceof Definition && $this->definitionVariables->contains($callable[0]))
            ) {
                return $return.\sprintf('%s->%s(%s)', $this->dumpValue($callable[0]), $callable[1], $arguments ? implode(', ', $arguments) : '').$tail;
            }

            $class = $this->dumpValue($callable[0]);
            // If the class is a string we can optimize away
            if (str_starts_with($class, "'") && !str_contains($class, '$')) {
                if ("''" === $class) {
                    throw new RuntimeException(\sprintf('Cannot dump definition: "%s" service is defined to be created by a factory but is missing the service reference, did you forget to define the factory service id or class?', $id ? 'The "'.$id.'"' : 'inline'));
                }

                return $return.\sprintf('%s::%s(%s)', $this->dumpLiteralClass($class), $callable[1], $arguments ? implode(', ', $arguments) : '').$tail;
            }

            if (str_starts_with($class, 'new ')) {
                return $return.\sprintf('(%s)->%s(%s)', $class, $callable[1], $arguments ? implode(', ', $arguments) : '').$tail;
            }

            return $return.\sprintf("[%s, '%s'](%s)", $class, $callable[1], $arguments ? implode(', ', $arguments) : '').$tail;
        }

        if (null === $class = $definition->getClass()) {
            throw new RuntimeException('Cannot dump definitions which have no class nor factory.');
        }

        if (!$asGhostObject) {
            return $return.\sprintf('new %s(%s)', $this->dumpLiteralClass($this->dumpValue($class)), implode(', ', $arguments)).$tail;
        }

        if (!method_exists($this->container->getParameterBag()->resolveValue($class), '__construct')) {
            return $return.'$lazyLoad'.$tail;
        }

        return $return.\sprintf('($lazyLoad->__construct(%s) && false ?: $lazyLoad)', implode(', ', $arguments)).$tail;
    }

    private function startClass(string $class, string $baseClass, bool $hasProxyClasses): string
    {
        $namespaceLine = !$this->asFiles && $this->namespace ? "\nnamespace {$this->namespace};\n" : '';

        $code = <<<EOF
<?php
$namespaceLine
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/*{$this->docStar}
 * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
 */
class $class extends $baseClass
{
    private const DEPRECATED_PARAMETERS = [];

    private const NONEMPTY_PARAMETERS = [];

    protected \$parameters = [];

    public function __construct()
    {

EOF;
        $code = str_replace("    private const DEPRECATED_PARAMETERS = [];\n\n", $this->addDeprecatedParameters(), $code);
        $code = str_replace("    private const NONEMPTY_PARAMETERS = [];\n\n", $this->addNonEmptyParameters(), $code);

        if ($this->asFiles) {
            $code = str_replace('__construct()', '__construct(private array $buildParameters = [], protected string $containerDir = __DIR__)', $code);

            if (null !== $this->targetDirRegex) {
                $code = str_replace('$parameters = []', "\$targetDir;\n    protected \$parameters = []", $code);
                $code .= '        $this->targetDir = \\dirname($containerDir);'."\n";
            }
        }

        if (Container::class !== $this->baseClass) {
            $r = $this->container->getReflectionClass($this->baseClass, false);
            if (null !== $r
                && (null !== $constructor = $r->getConstructor())
                && 0 === $constructor->getNumberOfRequiredParameters()
                && Container::class !== $constructor->getDeclaringClass()->name
            ) {
                $code .= "        parent::__construct();\n";
                $code .= "        \$this->parameterBag = null;\n\n";
            }
        }

        if ($this->container->getParameterBag()->all()) {
            $code .= "        \$this->parameters = \$this->getDefaultParameters();\n\n";
        }
        $code .= "        \$this->services = \$this->privates = [];\n";

        $code .= $this->addSyntheticIds();
        $code .= $this->addMethodMap();
        $code .= $this->asFiles && !$this->inlineFactories ? $this->addFileMap() : '';
        $code .= $this->addAliases();
        $code .= $this->addInlineRequires($hasProxyClasses);
        $code .= <<<EOF
    }

    public function compile(): void
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled(): bool
    {
        return true;
    }

EOF;
        $code .= $this->addRemovedIds();

        if ($this->asFiles && !$this->inlineFactories) {
            $code .= <<<'EOF'

    protected function load($file, $lazyLoad = true): mixed
    {
        if (class_exists($class = __NAMESPACE__.'\\'.$file, false)) {
            return $class::do($this, $lazyLoad);
        }

        if ('.' === $file[-4]) {
            $class = substr($class, 0, -4);
        } else {
            $file .= '.php';
        }

        $service = require $this->containerDir.\DIRECTORY_SEPARATOR.$file;

        return class_exists($class, false) ? $class::do($this, $lazyLoad) : $service;
    }

EOF;
        }

        foreach ($this->container->getDefinitions() as $definition) {
            if (!$definition->isLazy() || !$this->hasProxyDumper) {
                continue;
            }

            if ($this->asFiles && !$this->inlineFactories) {
                $proxyLoader = "class_exists(\$class, false) || require __DIR__.'/'.\$class.'.php';\n\n        ";
            } else {
                $proxyLoader = '';
            }

            $code .= <<<EOF

    protected function createProxy(\$class, \Closure \$factory)
    {
        {$proxyLoader}return \$factory();
    }

EOF;
            break;
        }

        return $code;
    }

    private function addSyntheticIds(): string
    {
        $code = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if ($definition->isSynthetic() && 'service_container' !== $id) {
                $code .= '            '.$this->doExport($id)." => true,\n";
            }
        }

        return $code ? "        \$this->syntheticIds = [\n{$code}        ];\n" : '';
    }

    private function addRemovedIds(): string
    {
        $ids = $this->container->getRemovedIds();
        foreach ($this->container->getDefinitions() as $id => $definition) {
            if (!$definition->isPublic() && '.' !== ($id[0] ?? '-')) {
                $ids[$id] = true;
            }
        }
        if (!$ids) {
            return '';
        }
        if ($this->asFiles) {
            $code = "require \$this->containerDir.\\DIRECTORY_SEPARATOR.'removed-ids.php'";
        } else {
            $code = '';
            $ids = array_keys($ids);
            sort($ids);
            foreach ($ids as $id) {
                if (preg_match(FileLoader::ANONYMOUS_ID_REGEXP, $id)) {
                    continue;
                }
                $code .= '            '.$this->doExport($id)." => true,\n";
            }

            $code = "[\n{$code}        ]";
        }

        return <<<EOF

    public function getRemovedIds(): array
    {
        return {$code};
    }

EOF;
    }

    private function addDeprecatedParameters(): string
    {
        if (!($bag = $this->container->getParameterBag()) instanceof ParameterBag) {
            return '';
        }

        if (!$deprecated = $bag->allDeprecated()) {
            return '';
        }
        $code = '';
        ksort($deprecated);
        foreach ($deprecated as $param => $deprecation) {
            $code .= '        '.$this->doExport($param).' => ['.implode(', ', array_map($this->doExport(...), $deprecation))."],\n";
        }

        return "    private const DEPRECATED_PARAMETERS = [\n{$code}    ];\n\n";
    }

    private function addNonEmptyParameters(): string
    {
        if (!($bag = $this->container->getParameterBag()) instanceof ParameterBag) {
            return '';
        }

        if (!$nonEmpty = $bag->allNonEmpty()) {
            return '';
        }
        $code = '';
        ksort($nonEmpty);
        foreach ($nonEmpty as $param => $message) {
            $code .= '        '.$this->doExport($param).' => '.$this->doExport($message).",\n";
        }

        return "    private const NONEMPTY_PARAMETERS = [\n{$code}    ];\n\n";
    }

    private function addMethodMap(): string
    {
        $code = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->isSynthetic() && $definition->isPublic() && (!$this->asFiles || $this->inlineFactories || $this->isHotPath($definition))) {
                $code .= '            '.$this->doExport($id).' => '.$this->doExport($this->generateMethodName($id)).",\n";
            }
        }

        $aliases = $this->container->getAliases();
        foreach ($aliases as $alias => $id) {
            if (!$id->isDeprecated()) {
                continue;
            }
            $code .= '            '.$this->doExport($alias).' => '.$this->doExport($this->generateMethodName($alias)).",\n";
        }

        return $code ? "        \$this->methodMap = [\n{$code}        ];\n" : '';
    }

    private function addFileMap(): string
    {
        $code = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->isSynthetic() && $definition->isPublic() && !$this->isHotPath($definition)) {
                $code .= \sprintf("            %s => '%s',\n", $this->doExport($id), $this->generateMethodName($id));
            }
        }

        return $code ? "        \$this->fileMap = [\n{$code}        ];\n" : '';
    }

    private function addAliases(): string
    {
        if (!$aliases = $this->container->getAliases()) {
            return "\n        \$this->aliases = [];\n";
        }

        $code = "        \$this->aliases = [\n";
        ksort($aliases);
        foreach ($aliases as $alias => $id) {
            if ($id->isDeprecated()) {
                continue;
            }

            $id = (string) $id;
            while (isset($aliases[$id])) {
                $id = (string) $aliases[$id];
            }
            $code .= '            '.$this->doExport($alias).' => '.$this->doExport($id).",\n";
        }

        return $code."        ];\n";
    }

    private function addDeprecatedAliases(): string
    {
        $code = '';
        $aliases = $this->container->getAliases();
        foreach ($aliases as $alias => $definition) {
            if (!$definition->isDeprecated()) {
                continue;
            }
            $public = $definition->isPublic() ? 'public' : 'private';
            $id = (string) $definition;
            $methodNameAlias = $this->generateMethodName($alias);
            $idExported = $this->export($id);
            $deprecation = $definition->getDeprecation($alias);
            $packageExported = $this->export($deprecation['package']);
            $versionExported = $this->export($deprecation['version']);
            $messageExported = $this->export($deprecation['message']);
            $code .= <<<EOF

    /*{$this->docStar}
     * Gets the $public '$alias' alias.
     *
     * @return object The "$id" service.
     */
    protected static function {$methodNameAlias}(\$container)
    {
        trigger_deprecation($packageExported, $versionExported, $messageExported);

        return \$container->get($idExported);
    }

EOF;
        }

        return $code;
    }

    private function addInlineRequires(bool $hasProxyClasses): string
    {
        $lineage = [];
        $hotPathServices = $this->hotPathTag && $this->inlineRequires ? $this->container->findTaggedServiceIds($this->hotPathTag) : [];

        foreach ($hotPathServices as $id => $tags) {
            $definition = $this->container->getDefinition($id);

            if ($definition->isLazy() && $this->hasProxyDumper) {
                continue;
            }

            $inlinedDefinitions = $this->getDefinitionsFromArguments([$definition]);

            foreach ($inlinedDefinitions as $def) {
                foreach ($this->getClasses($def, $id) as $class) {
                    $this->collectLineage($class, $lineage);
                }
            }
        }

        $code = '';

        foreach ($lineage as $file) {
            if (!isset($this->inlinedRequires[$file])) {
                $this->inlinedRequires[$file] = true;
                $code .= \sprintf("\n            include_once %s;", $file);
            }
        }

        if ($hasProxyClasses) {
            $code .= "\n            include_once __DIR__.'/proxy-classes.php';";
        }

        return $code ? \sprintf("\n        \$this->privates['service_container'] = static function (\$container) {%s\n        };\n", $code) : '';
    }

    private function addDefaultParametersMethod(): string
    {
        $bag = $this->container->getParameterBag();

        if (!$bag->all() && (!$bag instanceof ParameterBag || !$bag->allNonEmpty())) {
            return '';
        }

        $php = [];
        $dynamicPhp = [];

        foreach ($bag->all() as $key => $value) {
            if ($key !== $resolvedKey = $this->container->resolveEnvPlaceholders($key)) {
                throw new InvalidArgumentException(\sprintf('Parameter name cannot use env parameters: "%s".', $resolvedKey));
            }
            $hasEnum = false;
            $export = $this->exportParameters([$value], '', 12, $hasEnum);
            $export = explode('0 => ', substr(rtrim($export, " ]\n"), 2, -1), 2);

            if ($hasEnum || preg_match("/\\\$container->(?:getEnv\('(?:[-.\w\\\\]*+:)*+\w*+'\)|targetDir\.'')/", $export[1])) {
                $dynamicPhp[$key] = \sprintf('%s%s => %s,', $export[0], $this->export($key), $export[1]);
                $this->dynamicParameters[$key] = true;
            } else {
                $php[] = \sprintf('%s%s => %s,', $export[0], $this->export($key), $export[1]);
            }
        }
        $parameters = \sprintf("[\n%s\n%s]", implode("\n", $php), str_repeat(' ', 8));

        $code = <<<'EOF'

    public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        if (isset(self::DEPRECATED_PARAMETERS[$name])) {
            trigger_deprecation(...self::DEPRECATED_PARAMETERS[$name]);
        }

        if (isset($this->buildParameters[$name])) {
            return $this->buildParameters[$name];
        }

        if (!(isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || \array_key_exists($name, $this->parameters))) {
            throw new ParameterNotFoundException($name, extraMessage: self::NONEMPTY_PARAMETERS[$name] ?? null);
        }

        if (isset($this->loadedDynamicParameters[$name])) {
            $value = $this->loadedDynamicParameters[$name] ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
        } else {
            $value = $this->parameters[$name];
        }

        if (isset(self::NONEMPTY_PARAMETERS[$name]) && (null === $value || '' === $value || [] === $value)) {
            throw new \Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException(self::NONEMPTY_PARAMETERS[$name]);
        }

        return $value;
    }

    public function hasParameter(string $name): bool
    {
        if (isset($this->buildParameters[$name])) {
            return true;
        }

        return isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || \array_key_exists($name, $this->parameters);
    }

    public function setParameter(string $name, $value): void
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    public function getParameterBag(): ParameterBagInterface
    {
        if (!isset($this->parameterBag)) {
            $parameters = $this->parameters;
            foreach ($this->loadedDynamicParameters as $name => $loaded) {
                $parameters[$name] = $loaded ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
            }
            foreach ($this->buildParameters as $name => $value) {
                $parameters[$name] = $value;
            }
            $this->parameterBag = new FrozenParameterBag($parameters, self::DEPRECATED_PARAMETERS, self::NONEMPTY_PARAMETERS);
        }

        return $this->parameterBag;
    }

EOF;

        if (!$this->asFiles) {
            $code = preg_replace('/^.*buildParameters.*\n.*\n.*\n\n?/m', '', $code);
        }

        if (!$bag instanceof ParameterBag || !$bag->allDeprecated()) {
            $code = preg_replace("/\n.*DEPRECATED_PARAMETERS.*\n.*\n.*\n/m", '', $code, 1);
            $code = str_replace(', self::DEPRECATED_PARAMETERS', ', []', $code);
        }

        if (!$bag instanceof ParameterBag || !$bag->allNonEmpty()) {
            $code = str_replace(', extraMessage: self::NONEMPTY_PARAMETERS[$name] ?? null', '', $code);
            $code = str_replace(', self::NONEMPTY_PARAMETERS', '', $code);
            $code = preg_replace("/\n.*NONEMPTY_PARAMETERS.*\n.*\n.*\n/m", '', $code, 1);
        }

        if ($dynamicPhp) {
            $loadedDynamicParameters = $this->exportParameters(array_combine(array_keys($dynamicPhp), array_fill(0, \count($dynamicPhp), false)), '', 8);
            $getDynamicParameter = <<<'EOF'
        $container = $this;
        $value = match ($name) {
%s
            default => throw new ParameterNotFoundException($name),
        };
        $this->loadedDynamicParameters[$name] = true;

        return $this->dynamicParameters[$name] = $value;
EOF;
            $getDynamicParameter = \sprintf($getDynamicParameter, implode("\n", $dynamicPhp));
        } else {
            $loadedDynamicParameters = '[]';
            $getDynamicParameter = str_repeat(' ', 8).'throw new ParameterNotFoundException($name);';
        }

        return $code.<<<EOF

    private \$loadedDynamicParameters = {$loadedDynamicParameters};
    private \$dynamicParameters = [];

    private function getDynamicParameter(string \$name)
    {
{$getDynamicParameter}
    }

    protected function getDefaultParameters(): array
    {
        return $parameters;
    }

EOF;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function exportParameters(array $parameters, string $path = '', int $indent = 12, bool &$hasEnum = false): string
    {
        $php = [];
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $value = $this->exportParameters($value, $path.'/'.$key, $indent + 4, $hasEnum);
            } elseif ($value instanceof ArgumentInterface) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain special arguments. "%s" found in "%s".', get_debug_type($value), $path.'/'.$key));
            } elseif ($value instanceof Variable) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain variable references. Variable "%s" found in "%s".', $value, $path.'/'.$key));
            } elseif ($value instanceof Definition) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain service definitions. Definition for "%s" found in "%s".', $value->getClass(), $path.'/'.$key));
            } elseif ($value instanceof Reference) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain references to other services (reference to service "%s" found in "%s").', $value, $path.'/'.$key));
            } elseif ($value instanceof Expression) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain expressions. Expression "%s" found in "%s".', $value, $path.'/'.$key));
            } elseif ($value instanceof \UnitEnum) {
                $hasEnum = true;
                $value = \sprintf('\%s::%s', $value::class, $value->name);
            } else {
                $value = $this->export($value);
            }

            $php[] = \sprintf('%s%s => %s,', str_repeat(' ', $indent), $this->export($key), $value);
        }

        return \sprintf("[\n%s\n%s]", implode("\n", $php), str_repeat(' ', $indent - 4));
    }

    private function endClass(): string
    {
        return <<<'EOF'
}

EOF;
    }

    private function wrapServiceConditionals(mixed $value, string $code): string
    {
        if (!$condition = $this->getServiceConditionals($value)) {
            return $code;
        }

        // re-indent the wrapped code
        $code = implode("\n", array_map(fn ($line) => $line ? '    '.$line : $line, explode("\n", $code)));

        return \sprintf("        if (%s) {\n%s        }\n", $condition, $code);
    }

    private function getServiceConditionals(mixed $value): string
    {
        $conditions = [];
        foreach (ContainerBuilder::getInitializedConditionals($value) as $service) {
            if (!$this->container->hasDefinition($service)) {
                return 'false';
            }
            $conditions[] = \sprintf('isset($container->%s[%s])', $this->container->getDefinition($service)->isPublic() ? 'services' : 'privates', $this->doExport($service));
        }
        foreach (ContainerBuilder::getServiceConditionals($value) as $service) {
            if ($this->container->hasDefinition($service) && !$this->container->getDefinition($service)->isPublic()) {
                continue;
            }

            $conditions[] = \sprintf('$container->has(%s)', $this->doExport($service));
        }

        if (!$conditions) {
            return '';
        }

        return implode(' && ', $conditions);
    }

    private function getDefinitionsFromArguments(array $arguments, ?\SplObjectStorage $definitions = null, array &$calls = [], ?bool $byConstructor = null): \SplObjectStorage
    {
        $definitions ??= new \SplObjectStorage();

        foreach ($arguments as $argument) {
            if (\is_array($argument)) {
                $this->getDefinitionsFromArguments($argument, $definitions, $calls, $byConstructor);
            } elseif ($argument instanceof Reference) {
                $id = (string) $argument;

                while ($this->container->hasAlias($id)) {
                    $id = (string) $this->container->getAlias($id);
                }

                if (!isset($calls[$id])) {
                    $calls[$id] = [0, $argument->getInvalidBehavior(), $byConstructor];
                } else {
                    $calls[$id][1] = min($calls[$id][1], $argument->getInvalidBehavior());
                }

                ++$calls[$id][0];
            } elseif (!$argument instanceof Definition) {
                // no-op
            } elseif (isset($definitions[$argument])) {
                $definitions[$argument] = 1 + $definitions[$argument];
            } else {
                $definitions[$argument] = 1;
                $arguments = [$argument->getArguments(), $argument->getFactory()];
                $this->getDefinitionsFromArguments($arguments, $definitions, $calls, null === $byConstructor || $byConstructor);
                $arguments = [$argument->getProperties(), $argument->getMethodCalls(), $argument->getConfigurator()];
                $this->getDefinitionsFromArguments($arguments, $definitions, $calls, null !== $byConstructor && $byConstructor);
            }
        }

        return $definitions;
    }

    /**
     * @throws RuntimeException
     */
    private function dumpValue(mixed $value, bool $interpolate = true): string
    {
        if (\is_array($value)) {
            if ($value && $interpolate && false !== $param = array_search($value, $this->container->getParameterBag()->all(), true)) {
                return $this->dumpValue("%$param%");
            }
            $isList = array_is_list($value);
            $code = [];
            foreach ($value as $k => $v) {
                $code[] = $isList ? $this->dumpValue($v, $interpolate) : \sprintf('%s => %s', $this->dumpValue($k, $interpolate), $this->dumpValue($v, $interpolate));
            }

            return \sprintf('[%s]', implode(', ', $code));
        } elseif ($value instanceof ArgumentInterface) {
            $scope = [$this->definitionVariables, $this->referenceVariables];
            $this->definitionVariables = $this->referenceVariables = null;

            try {
                if ($value instanceof ServiceClosureArgument) {
                    $value = $value->getValues()[0];
                    $code = $this->dumpValue($value, $interpolate);

                    $returnedType = '';
                    if ($value instanceof TypedReference) {
                        $returnedType = \sprintf(': %s\%s', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE >= $value->getInvalidBehavior() ? '' : '?', str_replace(['|', '&'], ['|\\', '&\\'], $value->getType()));
                    }

                    $attribute = '';
                    if ($value instanceof Reference) {
                        $attribute = 'name: '.$this->dumpValue((string) $value, $interpolate);

                        if ($this->container->hasDefinition($value) && ($class = $this->container->findDefinition($value)->getClass()) && $class !== (string) $value) {
                            $attribute .= ', class: '.$this->dumpValue($class, $interpolate);
                        }

                        $attribute = \sprintf('#[\Closure(%s)] ', $attribute);
                    }

                    return \sprintf('%sfn ()%s => %s', $attribute, $returnedType, $code);
                }

                if ($value instanceof IteratorArgument) {
                    if (!$values = $value->getValues()) {
                        return 'new RewindableGenerator(fn () => new \EmptyIterator(), 0)';
                    }

                    $code = [];
                    $code[] = 'new RewindableGenerator(function () use ($container) {';

                    $operands = [0];
                    foreach ($values as $k => $v) {
                        ($c = $this->getServiceConditionals($v)) ? $operands[] = "(int) ($c)" : ++$operands[0];
                        $v = $this->wrapServiceConditionals($v, \sprintf("        yield %s => %s;\n", $this->dumpValue($k, $interpolate), $this->dumpValue($v, $interpolate)));
                        foreach (explode("\n", $v) as $v) {
                            if ($v) {
                                $code[] = '    '.$v;
                            }
                        }
                    }

                    $code[] = \sprintf('        }, %s)', \count($operands) > 1 ? 'fn () => '.implode(' + ', $operands) : $operands[0]);

                    return implode("\n", $code);
                }

                if ($value instanceof ServiceLocatorArgument) {
                    $serviceMap = '';
                    $serviceTypes = '';
                    foreach ($value->getValues() as $k => $v) {
                        if (!$v instanceof Reference) {
                            $serviceMap .= \sprintf("\n            %s => [%s],", $this->export($k), $this->dumpValue($v));
                            $serviceTypes .= \sprintf("\n            %s => '?',", $this->export($k));
                            continue;
                        }
                        $id = (string) $v;
                        while ($this->container->hasAlias($id)) {
                            $id = (string) $this->container->getAlias($id);
                        }
                        $definition = $this->container->getDefinition($id);
                        $load = !($definition->hasErrors() && $e = $definition->getErrors()) ? $this->asFiles && !$this->inlineFactories && !$this->isHotPath($definition) : reset($e);
                        $serviceMap .= \sprintf("\n            %s => [%s, %s, %s, %s],",
                            $this->export($k),
                            $this->export($definition->isShared() ? ($definition->isPublic() ? 'services' : 'privates') : false),
                            $this->doExport($id),
                            $this->export(ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE !== $v->getInvalidBehavior() && !\is_string($load) ? $this->generateMethodName($id) : null),
                            $this->export($load)
                        );
                        $serviceTypes .= \sprintf("\n            %s => %s,", $this->export($k), $this->export($v instanceof TypedReference ? $v->getType() : '?'));
                        $this->locatedIds[$id] = true;
                    }
                    $this->addGetService = true;

                    return \sprintf('new \%s($container->getService ??= $container->getService(...), [%s%s], [%s%s])', ServiceLocator::class, $serviceMap, $serviceMap ? "\n        " : '', $serviceTypes, $serviceTypes ? "\n        " : '');
                }
            } finally {
                [$this->definitionVariables, $this->referenceVariables] = $scope;
            }
        } elseif ($value instanceof Definition) {
            if ($value->hasErrors() && $e = $value->getErrors()) {
                return \sprintf('throw new RuntimeException(%s)', $this->export(reset($e)));
            }
            if ($this->definitionVariables?->contains($value)) {
                return $this->dumpValue($this->definitionVariables[$value], $interpolate);
            }
            if ($value->getMethodCalls()) {
                throw new RuntimeException('Cannot dump definitions which have method calls.');
            }
            if ($value->getProperties()) {
                throw new RuntimeException('Cannot dump definitions which have properties.');
            }
            if (null !== $value->getConfigurator()) {
                throw new RuntimeException('Cannot dump definitions which have a configurator.');
            }

            return $this->addNewInstance($value);
        } elseif ($value instanceof Variable) {
            return '$'.$value;
        } elseif ($value instanceof Reference) {
            $id = (string) $value;

            while ($this->container->hasAlias($id)) {
                $id = (string) $this->container->getAlias($id);
            }

            if (null !== $this->referenceVariables && isset($this->referenceVariables[$id])) {
                return $this->dumpValue($this->referenceVariables[$id], $interpolate);
            }

            return $this->getServiceCall($id, $value);
        } elseif ($value instanceof Expression) {
            return $this->getExpressionLanguage()->compile((string) $value, ['container' => 'container']);
        } elseif ($value instanceof Parameter) {
            return $this->dumpParameter($value);
        } elseif (true === $interpolate && \is_string($value)) {
            if (preg_match('/^%([^%]+)%$/', $value, $match)) {
                // we do this to deal with non string values (Boolean, integer, ...)
                // the preg_replace_callback converts them to strings
                return $this->dumpParameter($match[1]);
            }

            $replaceParameters = fn ($match) => "'.".$this->dumpParameter($match[2]).".'";

            return str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replaceParameters, $this->export($value)));
        } elseif ($value instanceof \UnitEnum) {
            return \sprintf('\%s::%s', $value::class, $value->name);
        } elseif ($value instanceof AbstractArgument) {
            throw new RuntimeException($value->getTextWithContext());
        } elseif (\is_object($value) || \is_resource($value)) {
            throw new RuntimeException(\sprintf('Unable to dump a service container if a parameter is an object or a resource, got "%s".', get_debug_type($value)));
        }

        return $this->export($value);
    }

    /**
     * Dumps a string to a literal (aka PHP Code) class value.
     *
     * @throws RuntimeException
     */
    private function dumpLiteralClass(string $class): string
    {
        if (str_contains($class, '$')) {
            return \sprintf('${($_ = %s) && false ?: "_"}', $class);
        }
        if (!str_starts_with($class, "'") || !preg_match('/^\'(?:\\\{2})?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new RuntimeException(\sprintf('Cannot dump definition because of invalid class name (%s).', $class ?: 'n/a'));
        }

        $class = substr(str_replace('\\\\', '\\', $class), 1, -1);

        return str_starts_with($class, '\\') ? $class : '\\'.$class;
    }

    private function dumpParameter(string $name): string
    {
        if (!$this->container->hasParameter($name) || ($this->dynamicParameters[$name] ?? false)) {
            return \sprintf('$container->getParameter(%s)', $this->doExport($name));
        }

        $value = $this->container->getParameter($name);
        $dumpedValue = $this->dumpValue($value, false);

        if (!$value || !\is_array($value)) {
            return $dumpedValue;
        }

        return \sprintf('$container->parameters[%s]', $this->doExport($name));
    }

    private function getServiceCall(string $id, ?Reference $reference = null): string
    {
        while ($this->container->hasAlias($id)) {
            $id = (string) $this->container->getAlias($id);
        }

        if ('service_container' === $id) {
            return '$container';
        }

        if ($this->container->hasDefinition($id) && $definition = $this->container->getDefinition($id)) {
            if ($definition->isSynthetic()) {
                $code = \sprintf('$container->get(%s%s)', $this->doExport($id), null !== $reference ? ', '.$reference->getInvalidBehavior() : '');
            } elseif (null !== $reference && ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE === $reference->getInvalidBehavior()) {
                $code = 'null';
                if (!$definition->isShared()) {
                    return $code;
                }
            } elseif ($this->isTrivialInstance($definition)) {
                if ($definition->hasErrors() && $e = $definition->getErrors()) {
                    return \sprintf('throw new RuntimeException(%s)', $this->export(reset($e)));
                }
                $code = $this->addNewInstance($definition, '', $id);
                if ($definition->isShared() && !isset($this->singleUsePrivateIds[$id])) {
                    return \sprintf('($container->%s[%s] ??= %s)', $definition->isPublic() ? 'services' : 'privates', $this->doExport($id), $code);
                }
                $code = "($code)";
            } else {
                $code = $this->asFiles && !$this->inlineFactories && !$this->isHotPath($definition) ? "\$container->load('%s')" : 'self::%s($container)';
                $code = \sprintf($code, $this->generateMethodName($id));

                if (!$definition->isShared()) {
                    $factory = \sprintf('$container->factories%s[%s]', $definition->isPublic() ? '' : "['service_container']", $this->doExport($id));
                    $code = \sprintf('(isset(%s) ? %1$s($container) : %s)', $factory, $code);
                }
            }
            if ($definition->isShared() && !isset($this->singleUsePrivateIds[$id])) {
                $code = \sprintf('($container->%s[%s] ?? %s)', $definition->isPublic() ? 'services' : 'privates', $this->doExport($id), $code);
            }

            return $code;
        }
        if (null !== $reference && ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE === $reference->getInvalidBehavior()) {
            return 'null';
        }
        if (null !== $reference && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE < $reference->getInvalidBehavior()) {
            $code = \sprintf('$container->get(%s, ContainerInterface::NULL_ON_INVALID_REFERENCE)', $this->doExport($id));
        } else {
            $code = \sprintf('$container->get(%s)', $this->doExport($id));
        }

        return \sprintf('($container->services[%s] ?? %s)', $this->doExport($id), $code);
    }

    /**
     * Initializes the method names map to avoid conflicts with the Container methods.
     */
    private function initializeMethodNamesMap(string $class): void
    {
        $this->serviceIdToMethodNameMap = [];
        $this->usedMethodNames = [];

        if ($reflectionClass = $this->container->getReflectionClass($class)) {
            foreach ($reflectionClass->getMethods() as $method) {
                $this->usedMethodNames[strtolower($method->getName())] = true;
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function generateMethodName(string $id): string
    {
        if (isset($this->serviceIdToMethodNameMap[$id])) {
            return $this->serviceIdToMethodNameMap[$id];
        }

        $i = strrpos($id, '\\');
        $name = Container::camelize(false !== $i && isset($id[1 + $i]) ? substr($id, 1 + $i) : $id);
        $name = preg_replace('/[^a-zA-Z0-9_\x7f-\xff]/', '', $name);
        $methodName = 'get'.$name.'Service';
        $suffix = 1;

        while (isset($this->usedMethodNames[strtolower($methodName)])) {
            ++$suffix;
            $methodName = 'get'.$name.$suffix.'Service';
        }

        $this->serviceIdToMethodNameMap[$id] = $methodName;
        $this->usedMethodNames[strtolower($methodName)] = true;

        return $methodName;
    }

    private function getNextVariableName(): string
    {
        $firstChars = self::FIRST_CHARS;
        $firstCharsLength = \strlen($firstChars);
        $nonFirstChars = self::NON_FIRST_CHARS;
        $nonFirstCharsLength = \strlen($nonFirstChars);

        while (true) {
            $name = '';
            $i = $this->variableCount;
            $name .= $firstChars[$i % $firstCharsLength];
            $i = (int) ($i / $firstCharsLength);

            while ($i > 0) {
                --$i;
                $name .= $nonFirstChars[$i % $nonFirstCharsLength];
                $i = (int) ($i / $nonFirstCharsLength);
            }

            ++$this->variableCount;

            // check that the name is not reserved
            if (\in_array($name, $this->reservedVariables, true)) {
                continue;
            }

            return $name;
        }
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        if (!isset($this->expressionLanguage)) {
            if (!class_exists(\Symfony\Component\ExpressionLanguage\ExpressionLanguage::class)) {
                throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
            }
            $providers = $this->container->getExpressionLanguageProviders();
            $this->expressionLanguage = new ExpressionLanguage(null, $providers, function ($arg) {
                $id = '""' === substr_replace($arg, '', 1, -1) ? stripcslashes(substr($arg, 1, -1)) : null;

                if (null !== $id && ($this->container->hasAlias($id) || $this->container->hasDefinition($id))) {
                    return $this->getServiceCall($id);
                }

                return \sprintf('$container->get(%s)', $arg);
            });

            if ($this->container->isTrackingResources()) {
                foreach ($providers as $provider) {
                    $this->container->addObjectResource($provider);
                }
            }
        }

        return $this->expressionLanguage;
    }

    private function isHotPath(Definition $definition): bool
    {
        return $this->hotPathTag && $definition->hasTag($this->hotPathTag) && !$definition->isDeprecated();
    }

    private function isSingleUsePrivateNode(ServiceReferenceGraphNode $node): bool
    {
        if ($node->getValue()->isPublic()) {
            return false;
        }
        $ids = [];
        foreach ($node->getInEdges() as $edge) {
            if (!$value = $edge->getSourceNode()->getValue()) {
                continue;
            }
            if ($edge->isLazy() || !$value instanceof Definition || !$value->isShared()) {
                return false;
            }
            $ids[$edge->getSourceNode()->getId()] = true;
        }

        return 1 === \count($ids);
    }

    private function export(mixed $value): mixed
    {
        if (null !== $this->targetDirRegex && \is_string($value) && preg_match($this->targetDirRegex, $value, $matches, \PREG_OFFSET_CAPTURE)) {
            $suffix = $matches[0][1] + \strlen($matches[0][0]);
            $matches[0][1] += \strlen($matches[1][0]);
            $prefix = $matches[0][1] ? $this->doExport(substr($value, 0, $matches[0][1]), true).'.' : '';

            if ('\\' === \DIRECTORY_SEPARATOR && isset($value[$suffix])) {
                $cookie = '\\'.random_int(100000, \PHP_INT_MAX);
                $suffix = '.'.$this->doExport(str_replace('\\', $cookie, substr($value, $suffix)), true);
                $suffix = str_replace('\\'.$cookie, "'.\\DIRECTORY_SEPARATOR.'", $suffix);
            } else {
                $suffix = isset($value[$suffix]) ? '.'.$this->doExport(substr($value, $suffix), true) : '';
            }

            $dirname = $this->asFiles ? '$container->containerDir' : '__DIR__';
            $offset = 2 + $this->targetDirMaxMatches - \count($matches);

            if (0 < $offset) {
                $dirname = \sprintf('\dirname(__DIR__, %d)', $offset + (int) $this->asFiles);
            } elseif ($this->asFiles) {
                $dirname = "\$container->targetDir.''"; // empty string concatenation on purpose
            }

            if ($prefix || $suffix) {
                return \sprintf('(%s%s%s)', $prefix, $dirname, $suffix);
            }

            return $dirname;
        }

        return $this->doExport($value, true);
    }

    private function doExport(mixed $value, bool $resolveEnv = false): mixed
    {
        $shouldCacheValue = $resolveEnv && \is_string($value);
        if ($shouldCacheValue && isset($this->exportedVariables[$value])) {
            return $this->exportedVariables[$value];
        }
        if (\is_string($value) && str_contains($value, "\n")) {
            $cleanParts = explode("\n", $value);
            $cleanParts = array_map(fn ($part) => var_export($part, true), $cleanParts);
            $export = implode('."\n".', $cleanParts);
        } else {
            $export = var_export($value, true);
        }

        if ($resolveEnv && "'" === $export[0] && $export !== $resolvedExport = $this->container->resolveEnvPlaceholders($export, "'.\$container->getEnv('string:%s').'")) {
            $export = $resolvedExport;
            if (str_ends_with($export, ".''")) {
                $export = substr($export, 0, -3);
                if ("'" === $export[1]) {
                    $export = substr_replace($export, '', 23, 7);
                }
            }
            if ("'" === $export[1]) {
                $export = substr($export, 3);
            }
        }

        if ($shouldCacheValue) {
            $this->exportedVariables[$value] = $export;
        }

        return $export;
    }

    private function getAutoloadFile(): ?string
    {
        $file = null;

        foreach (spl_autoload_functions() as $autoloader) {
            if (!\is_array($autoloader)) {
                continue;
            }

            if ($autoloader[0] instanceof DebugClassLoader) {
                $autoloader = $autoloader[0]->getClassLoader();
            }

            if (!\is_array($autoloader) || !$autoloader[0] instanceof ClassLoader || !$autoloader[0]->findFile(__CLASS__)) {
                continue;
            }

            foreach (get_declared_classes() as $class) {
                if (str_starts_with($class, 'ComposerAutoloaderInit') && $class::getLoader() === $autoloader[0]) {
                    $file = \dirname((new \ReflectionClass($class))->getFileName(), 2).'/autoload.php';

                    if (null !== $this->targetDirRegex && preg_match($this->targetDirRegex.'A', $file)) {
                        return $file;
                    }
                }
            }
        }

        return $file;
    }

    private function getClasses(Definition $definition, string $id): array
    {
        $classes = [];
        $resolve = $this->container->getParameterBag()->resolveValue(...);

        while ($definition instanceof Definition) {
            foreach ($definition->getTag($this->preloadTags[0]) as $tag) {
                if (!isset($tag['class'])) {
                    throw new InvalidArgumentException(\sprintf('Missing attribute "class" on tag "%s" for service "%s".', $this->preloadTags[0], $id));
                }

                $classes[] = trim($tag['class'], '\\');
            }

            if ($class = $definition->getClass()) {
                $classes[] = trim($resolve($class), '\\');
            }
            $factory = $definition->getFactory();

            if (!\is_array($factory)) {
                $factory = [$factory];
            }

            if (\is_string($factory[0])) {
                $factory[0] = $resolve($factory[0]);

                if (false !== $i = strrpos($factory[0], '::')) {
                    $factory[0] = substr($factory[0], 0, $i);
                }
                $classes[] = trim($factory[0], '\\');
            }

            $definition = $factory[0];
        }

        return $classes;
    }

    private function isProxyCandidate(Definition $definition, ?bool &$asGhostObject, string $id): ?Definition
    {
        $asGhostObject = false;

        if (['Closure', 'fromCallable'] === $definition->getFactory()) {
            return null;
        }

        if (!$definition->isLazy() || !$this->hasProxyDumper) {
            return null;
        }

        return $this->getProxyDumper()->isProxyCandidate($definition, $asGhostObject, $id) ? $definition : null;
    }

    /**
     * Removes comments from a PHP source string.
     *
     * We don't use the PHP php_strip_whitespace() function
     * as we want the content to be readable and well-formatted.
     */
    private static function stripComments(string $source): string
    {
        if (!\function_exists('token_get_all')) {
            return $source;
        }

        $rawChunk = '';
        $output = '';
        $tokens = token_get_all($source);
        $ignoreSpace = false;
        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];
            if (!isset($token[1]) || 'b"' === $token) {
                $rawChunk .= $token;
            } elseif (\T_START_HEREDOC === $token[0]) {
                $output .= $rawChunk.$token[1];
                do {
                    $token = $tokens[++$i];
                    $output .= isset($token[1]) && 'b"' !== $token ? $token[1] : $token;
                } while (\T_END_HEREDOC !== $token[0]);
                $rawChunk = '';
            } elseif (\T_WHITESPACE === $token[0]) {
                if ($ignoreSpace) {
                    $ignoreSpace = false;

                    continue;
                }

                // replace multiple new lines with a single newline
                $rawChunk .= preg_replace(['/\n{2,}/S'], "\n", $token[1]);
            } elseif (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT])) {
                if (!\in_array($rawChunk[\strlen($rawChunk) - 1], [' ', "\n", "\r", "\t"], true)) {
                    $rawChunk .= ' ';
                }
                $ignoreSpace = true;
            } else {
                $rawChunk .= $token[1];

                // The PHP-open tag already has a new-line
                if (\T_OPEN_TAG === $token[0]) {
                    $ignoreSpace = true;
                } else {
                    $ignoreSpace = false;
                }
            }
        }

        $output .= $rawChunk;

        unset($tokens, $rawChunk);
        gc_mem_caches();

        return $output;
    }
}
