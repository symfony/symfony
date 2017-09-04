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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Variable;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Exception\EnvParameterException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface as ProxyDumper;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\NullDumper;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Kernel;

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
     *
     * @var string
     */
    const FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * Characters that might appear in the generated variable name as any but the first character.
     *
     * @var string
     */
    const NON_FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_';

    private $inlinedDefinitions;
    private $definitionVariables;
    private $referenceVariables;
    private $variableCount;
    private $reservedVariables = array('instance', 'class');
    private $expressionLanguage;
    private $targetDirRegex;
    private $targetDirMaxMatches;
    private $docStar;
    private $serviceIdToMethodNameMap;
    private $usedMethodNames;
    private $namespace;
    private $asFiles;

    /**
     * @var \Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface
     */
    private $proxyDumper;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerBuilder $container)
    {
        if (!$container->isCompiled()) {
            @trigger_error('Dumping an uncompiled ContainerBuilder is deprecated since version 3.3 and will not be supported anymore in 4.0. Compile the container beforehand.', E_USER_DEPRECATED);
        }

        parent::__construct($container);

        $this->inlinedDefinitions = new \SplObjectStorage();
    }

    /**
     * Sets the dumper to be used when dumping proxies in the generated container.
     *
     * @param ProxyDumper $proxyDumper
     */
    public function setProxyDumper(ProxyDumper $proxyDumper)
    {
        $this->proxyDumper = $proxyDumper;
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
     * @param array $options An array of options
     *
     * @return string|array A PHP class representing the service container or an array of PHP files if the "as_files" option is set
     *
     * @throws EnvParameterException When an env var exists but has not been dumped
     */
    public function dump(array $options = array())
    {
        $this->targetDirRegex = null;
        $options = array_merge(array(
            'class' => 'ProjectServiceContainer',
            'base_class' => 'Container',
            'namespace' => '',
            'as_files' => false,
            'debug' => true,
        ), $options);

        $this->namespace = $options['namespace'];
        $this->asFiles = $options['as_files'];
        $this->initializeMethodNamesMap($options['base_class']);

        $this->docStar = $options['debug'] ? '*' : '';

        if (!empty($options['file']) && is_dir($dir = dirname($options['file']))) {
            // Build a regexp where the first root dirs are mandatory,
            // but every other sub-dir is optional up to the full path in $dir
            // Mandate at least 2 root dirs and not more that 5 optional dirs.

            $dir = explode(DIRECTORY_SEPARATOR, realpath($dir));
            $i = count($dir);

            if (3 <= $i) {
                $regex = '';
                $lastOptionalDir = $i > 8 ? $i - 5 : 3;
                $this->targetDirMaxMatches = $i - $lastOptionalDir;

                while (--$i >= $lastOptionalDir) {
                    $regex = sprintf('(%s%s)?', preg_quote(DIRECTORY_SEPARATOR.$dir[$i], '#'), $regex);
                }

                do {
                    $regex = preg_quote(DIRECTORY_SEPARATOR.$dir[$i], '#').$regex;
                } while (0 < --$i);

                $this->targetDirRegex = '#'.preg_quote($dir[0], '#').$regex.'#';
            }
        }

        $code =
            $this->startClass($options['class'], $options['base_class']).
            $this->addServices().
            $this->addDefaultParametersMethod().
            $this->endClass()
        ;

        if ($this->asFiles) {
            $fileStart = <<<EOF
<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;

// This file has been auto-generated by the Symfony Dependency Injection Component for internal use.

EOF;
            $files = array();
            foreach ($this->generateServiceFiles() as $file => $c) {
                $files[$file] = $fileStart.$c;
            }
            foreach ($this->generateProxyClasses() as $file => $c) {
                $files[$file] = "<?php\n".$c;
            }
            $files['Container.php'] = $code;
            $hash = ucfirst(strtr(ContainerBuilder::hash($files), '._', 'xx'));
            $code = array();

            foreach ($files as $file => $c) {
                $code["Container{$hash}/{$file}"] = $c;
            }
            array_pop($code);
            $code["Container{$hash}/Container.php"] = implode("\nclass Container{$hash}", explode("\nclass {$options['class']}", $files['Container.php'], 2));
            $namespaceLine = $this->namespace ? "\nnamespace {$this->namespace};\n" : '';

            $code[$options['class'].'.php'] = <<<EOF
<?php
{$namespaceLine}
// This file has been auto-generated by the Symfony Dependency Injection Component for internal use.

if (!class_exists(Container{$hash}::class, false)) {
    require __DIR__.'/Container{$hash}/Container.php';
}

if (!class_exists({$options['class']}::class, false)) {
    class_alias(Container{$hash}::class, {$options['class']}::class, false);
}

return new Container{$hash}();

EOF;
        } else {
            foreach ($this->generateProxyClasses() as $c) {
                $code .= $c;
            }
        }

        $this->targetDirRegex = null;

        $unusedEnvs = array();
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
     *
     * @return ProxyDumper
     */
    private function getProxyDumper()
    {
        if (!$this->proxyDumper) {
            $this->proxyDumper = new NullDumper();
        }

        return $this->proxyDumper;
    }

    /**
     * Generates Service local temp variables.
     *
     * @param string     $cId
     * @param Definition $definition
     * @param array      $inlinedDefinitions
     *
     * @return string
     */
    private function addServiceLocalTempVariables($cId, Definition $definition, array $inlinedDefinitions)
    {
        static $template = "        \$%s = %s;\n";

        array_unshift($inlinedDefinitions, $definition);

        $calls = $behavior = array();
        foreach ($inlinedDefinitions as $iDefinition) {
            $this->getServiceCallsFromArguments($iDefinition->getArguments(), $calls, $behavior);
            $this->getServiceCallsFromArguments($iDefinition->getMethodCalls(), $calls, $behavior);
            $this->getServiceCallsFromArguments($iDefinition->getProperties(), $calls, $behavior);
            $this->getServiceCallsFromArguments(array($iDefinition->getConfigurator()), $calls, $behavior);
            $this->getServiceCallsFromArguments(array($iDefinition->getFactory()), $calls, $behavior);
        }

        $code = '';
        foreach ($calls as $id => $callCount) {
            if ('service_container' === $id || $id === $cId) {
                continue;
            }

            if ($callCount > 1) {
                $name = $this->getNextVariableName();
                $this->referenceVariables[$id] = new Variable($name);

                if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $behavior[$id]) {
                    $code .= sprintf($template, $name, $this->getServiceCall($id));
                } else {
                    $code .= sprintf($template, $name, $this->getServiceCall($id, new Reference($id, $behavior[$id])));
                }
            }
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    /**
     * Generates code for the proxies.
     *
     * @return string
     */
    private function generateProxyClasses()
    {
        $definitions = $this->container->getDefinitions();
        $strip = '' === $this->docStar && method_exists('Symfony\Component\HttpKernel\Kernel', 'stripComments');
        $proxyDumper = $this->getProxyDumper();
        ksort($definitions);
        foreach ($definitions as $definition) {
            if (!$proxyDumper->isProxyCandidate($definition)) {
                continue;
            }
            $proxyCode = "\n".$proxyDumper->getProxyCode($definition);
            if ($strip) {
                $proxyCode = "<?php\n".$proxyCode;
                $proxyCode = substr(Kernel::stripComments($proxyCode), 5);
            }
            yield sprintf('%s.php', explode(' ', $proxyCode, 3)[1]) => $proxyCode;
        }
    }

    /**
     * Generates the require_once statement for service includes.
     *
     * @param Definition $definition
     * @param array      $inlinedDefinitions
     *
     * @return string
     */
    private function addServiceInclude(Definition $definition, array $inlinedDefinitions)
    {
        $template = "        require_once %s;\n";
        $code = '';

        if (null !== $file = $definition->getFile()) {
            $code .= sprintf($template, $this->dumpValue($file));
        }

        foreach ($inlinedDefinitions as $definition) {
            if (null !== $file = $definition->getFile()) {
                $code .= sprintf($template, $this->dumpValue($file));
            }
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    /**
     * Generates the inline definition of a service.
     *
     * @param string $id
     * @param array  $inlinedDefinitions
     *
     * @return string
     *
     * @throws RuntimeException                  When the factory definition is incomplete
     * @throws ServiceCircularReferenceException When a circular reference is detected
     */
    private function addServiceInlinedDefinitions($id, array $inlinedDefinitions)
    {
        $code = '';
        $variableMap = $this->definitionVariables;
        $nbOccurrences = new \SplObjectStorage();
        $processed = new \SplObjectStorage();

        foreach ($inlinedDefinitions as $definition) {
            if (false === $nbOccurrences->contains($definition)) {
                $nbOccurrences->offsetSet($definition, 1);
            } else {
                $i = $nbOccurrences->offsetGet($definition);
                $nbOccurrences->offsetSet($definition, $i + 1);
            }
        }

        foreach ($inlinedDefinitions as $sDefinition) {
            if ($processed->contains($sDefinition)) {
                continue;
            }
            $processed->offsetSet($sDefinition);

            $class = $this->dumpValue($sDefinition->getClass());
            if ($nbOccurrences->offsetGet($sDefinition) > 1 || $sDefinition->getMethodCalls() || $sDefinition->getProperties() || null !== $sDefinition->getConfigurator() || false !== strpos($class, '$')) {
                $name = $this->getNextVariableName();
                $variableMap->offsetSet($sDefinition, new Variable($name));

                // a construct like:
                // $a = new ServiceA(ServiceB $b); $b = new ServiceB(ServiceA $a);
                // this is an indication for a wrong implementation, you can circumvent this problem
                // by setting up your service structure like this:
                // $b = new ServiceB();
                // $a = new ServiceA(ServiceB $b);
                // $b->setServiceA(ServiceA $a);
                if ($this->hasReference($id, $sDefinition->getArguments())) {
                    throw new ServiceCircularReferenceException($id, array($id));
                }

                $code .= $this->addNewInstance($sDefinition, '$'.$name, ' = ', $id);

                if (!$this->hasReference($id, $sDefinition->getMethodCalls(), true) && !$this->hasReference($id, $sDefinition->getProperties(), true)) {
                    $code .= $this->addServiceProperties($sDefinition, $name);
                    $code .= $this->addServiceMethodCalls($sDefinition, $name);
                    $code .= $this->addServiceConfigurator($sDefinition, $name);
                }

                $code .= "\n";
            }
        }

        return $code;
    }

    /**
     * Generates the service instance.
     *
     * @param string     $id
     * @param Definition $definition
     * @param bool       $isSimpleInstance
     *
     * @return string
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function addServiceInstance($id, Definition $definition, $isSimpleInstance)
    {
        $class = $this->dumpValue($definition->getClass());

        if (0 === strpos($class, "'") && false === strpos($class, '$') && !preg_match('/^\'(?:\\\{2})?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid class name for the "%s" service.', $class, $id));
        }

        $isProxyCandidate = $this->getProxyDumper()->isProxyCandidate($definition);
        $instantiation = '';

        if (!$isProxyCandidate && $definition->isShared()) {
            $instantiation = "\$this->services['$id'] = ".($isSimpleInstance ? '' : '$instance');
        } elseif (!$isSimpleInstance) {
            $instantiation = '$instance';
        }

        $return = '';
        if ($isSimpleInstance) {
            $return = 'return ';
        } else {
            $instantiation .= ' = ';
        }

        $code = $this->addNewInstance($definition, $return, $instantiation, $id);

        if (!$isSimpleInstance) {
            $code .= "\n";
        }

        return $code;
    }

    /**
     * Checks if the definition is a simple instance.
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @return bool
     */
    private function isSimpleInstance($id, Definition $definition)
    {
        foreach (array_merge(array($definition), $this->getInlinedDefinitions($definition)) as $sDefinition) {
            if ($definition !== $sDefinition && !$this->hasReference($id, $sDefinition->getMethodCalls())) {
                continue;
            }

            if ($sDefinition->getMethodCalls() || $sDefinition->getProperties() || $sDefinition->getConfigurator()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if the definition is a trivial instance.
     *
     * @param Definition $definition
     *
     * @return bool
     */
    private function isTrivialInstance(Definition $definition)
    {
        if ($definition->isSynthetic() || $definition->getFile() || $definition->getMethodCalls() || $definition->getProperties() || $definition->getConfigurator()) {
            return false;
        }
        if ($definition->isDeprecated() || $definition->isLazy() || $definition->getFactory() || 3 < count($definition->getArguments())) {
            return false;
        }

        foreach ($definition->getArguments() as $arg) {
            if (!$arg || ($arg instanceof Reference && 'service_container' !== (string) $arg)) {
                continue;
            }
            if (is_array($arg) && 3 >= count($arg)) {
                foreach ($arg as $k => $v) {
                    if ($this->dumpValue($k) !== $this->dumpValue($k, false)) {
                        return false;
                    }
                    if (!$v || ($v instanceof Reference && 'service_container' !== (string) $v)) {
                        continue;
                    }
                    if (!is_scalar($v) || $this->dumpValue($v) !== $this->dumpValue($v, false)) {
                        return false;
                    }
                }
            } elseif (!is_scalar($arg) || $this->dumpValue($arg) !== $this->dumpValue($arg, false)) {
                return false;
            }
        }

        if (false !== strpos($this->dumpLiteralClass($this->dumpValue($definition->getClass())), '$')) {
            return false;
        }

        return true;
    }

    /**
     * Adds method calls to a service definition.
     *
     * @param Definition $definition
     * @param string     $variableName
     *
     * @return string
     */
    private function addServiceMethodCalls(Definition $definition, $variableName = 'instance')
    {
        $calls = '';
        foreach ($definition->getMethodCalls() as $call) {
            $arguments = array();
            foreach ($call[1] as $value) {
                $arguments[] = $this->dumpValue($value);
            }

            $calls .= $this->wrapServiceConditionals($call[1], sprintf("        \$%s->%s(%s);\n", $variableName, $call[0], implode(', ', $arguments)));
        }

        return $calls;
    }

    private function addServiceProperties(Definition $definition, $variableName = 'instance')
    {
        $code = '';
        foreach ($definition->getProperties() as $name => $value) {
            $code .= sprintf("        \$%s->%s = %s;\n", $variableName, $name, $this->dumpValue($value));
        }

        return $code;
    }

    /**
     * Generates the inline definition setup.
     *
     * @param string $id
     * @param array  $inlinedDefinitions
     * @param bool   $isSimpleInstance
     *
     * @return string
     *
     * @throws ServiceCircularReferenceException when the container contains a circular reference
     */
    private function addServiceInlinedDefinitionsSetup($id, array $inlinedDefinitions, $isSimpleInstance)
    {
        $this->referenceVariables[$id] = new Variable('instance');

        $code = '';
        $processed = new \SplObjectStorage();
        foreach ($inlinedDefinitions as $iDefinition) {
            if ($processed->contains($iDefinition)) {
                continue;
            }
            $processed->offsetSet($iDefinition);

            if (!$this->hasReference($id, $iDefinition->getMethodCalls(), true) && !$this->hasReference($id, $iDefinition->getProperties(), true)) {
                continue;
            }

            // if the instance is simple, the return statement has already been generated
            // so, the only possible way to get there is because of a circular reference
            if ($isSimpleInstance) {
                throw new ServiceCircularReferenceException($id, array($id));
            }

            $name = (string) $this->definitionVariables->offsetGet($iDefinition);
            $code .= $this->addServiceProperties($iDefinition, $name);
            $code .= $this->addServiceMethodCalls($iDefinition, $name);
            $code .= $this->addServiceConfigurator($iDefinition, $name);
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    /**
     * Adds configurator definition.
     *
     * @param Definition $definition
     * @param string     $variableName
     *
     * @return string
     */
    private function addServiceConfigurator(Definition $definition, $variableName = 'instance')
    {
        if (!$callable = $definition->getConfigurator()) {
            return '';
        }

        if (is_array($callable)) {
            if ($callable[0] instanceof Reference
                || ($callable[0] instanceof Definition && $this->definitionVariables->contains($callable[0]))) {
                return sprintf("        %s->%s(\$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
            }

            $class = $this->dumpValue($callable[0]);
            // If the class is a string we can optimize call_user_func away
            if (0 === strpos($class, "'") && false === strpos($class, '$')) {
                return sprintf("        %s::%s(\$%s);\n", $this->dumpLiteralClass($class), $callable[1], $variableName);
            }

            if (0 === strpos($class, 'new ')) {
                return sprintf("        (%s)->%s(\$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
            }

            return sprintf("        call_user_func(array(%s, '%s'), \$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
        }

        return sprintf("        %s(\$%s);\n", $callable, $variableName);
    }

    /**
     * Adds a service.
     *
     * @param string     $id
     * @param Definition $definition
     * @param string     &$file
     *
     * @return string
     */
    private function addService($id, Definition $definition, &$file = null)
    {
        $this->definitionVariables = new \SplObjectStorage();
        $this->referenceVariables = array();
        $this->variableCount = 0;

        $return = array();

        if ($class = $definition->getClass()) {
            $class = $this->container->resolveEnvPlaceholders($class);
            $return[] = sprintf(0 === strpos($class, '%') ? '@return object A %1$s instance' : '@return \%s', ltrim($class, '\\'));
        } elseif ($definition->getFactory()) {
            $factory = $definition->getFactory();
            if (is_string($factory)) {
                $return[] = sprintf('@return object An instance returned by %s()', $factory);
            } elseif (is_array($factory) && (is_string($factory[0]) || $factory[0] instanceof Definition || $factory[0] instanceof Reference)) {
                if (is_string($factory[0]) || $factory[0] instanceof Reference) {
                    $return[] = sprintf('@return object An instance returned by %s::%s()', (string) $factory[0], $factory[1]);
                } elseif ($factory[0] instanceof Definition) {
                    $return[] = sprintf('@return object An instance returned by %s::%s()', $factory[0]->getClass(), $factory[1]);
                }
            }
        }

        if ($definition->isDeprecated()) {
            if ($return && 0 === strpos($return[count($return) - 1], '@return')) {
                $return[] = '';
            }

            $return[] = sprintf('@deprecated %s', $definition->getDeprecationMessage($id));
        }

        $return = str_replace("\n     * \n", "\n     *\n", implode("\n     * ", $return));
        $return = $this->container->resolveEnvPlaceholders($return);

        $shared = $definition->isShared() ? ' shared' : '';
        $public = $definition->isPublic() ? 'public' : 'private';
        $autowired = $definition->isAutowired() ? ' autowired' : '';

        if ($definition->isLazy()) {
            $lazyInitialization = '$lazyLoad = true';
        } else {
            $lazyInitialization = '';
        }

        $asFile = $this->asFiles && $definition->isShared();
        $methodName = $this->generateMethodName($id);
        if ($asFile) {
            $file = $methodName.'.php';
            $code = "        // Returns the $public '$id'$shared$autowired service.\n\n";
        } else {
            $code = <<<EOF

    /*{$this->docStar}
     * Gets the $public '$id'$shared$autowired service.
     *
     * $return
     */
    protected function {$methodName}($lazyInitialization)
    {

EOF;
        }

        if ($this->getProxyDumper()->isProxyCandidate($definition)) {
            $factoryCode = $asFile ? "\$this->load(__DIR__.'/%s.php', false)" : '$this->%s(false)';
            $code .= $this->getProxyDumper()->getProxyFactoryCode($definition, $id, sprintf($factoryCode, $methodName));
        }

        if ($definition->isDeprecated()) {
            $code .= sprintf("        @trigger_error(%s, E_USER_DEPRECATED);\n\n", $this->export($definition->getDeprecationMessage($id)));
        }

        $inlinedDefinitions = $this->getInlinedDefinitions($definition);
        $isSimpleInstance = $this->isSimpleInstance($id, $definition, $inlinedDefinitions);

        $code .=
            $this->addServiceInclude($definition, $inlinedDefinitions).
            $this->addServiceLocalTempVariables($id, $definition, $inlinedDefinitions).
            $this->addServiceInlinedDefinitions($id, $inlinedDefinitions).
            $this->addServiceInstance($id, $definition, $isSimpleInstance).
            $this->addServiceInlinedDefinitionsSetup($id, $inlinedDefinitions, $isSimpleInstance).
            $this->addServiceProperties($definition).
            $this->addServiceMethodCalls($definition).
            $this->addServiceConfigurator($definition).
            (!$isSimpleInstance ? "\n        return \$instance;\n" : '')
        ;

        if ($asFile) {
            $code = implode("\n", array_map(function ($line) { return $line ? substr($line, 8) : $line; }, explode("\n", $code)));
        } else {
            $code .= "    }\n";
        }

        $this->definitionVariables = null;
        $this->referenceVariables = null;

        return $code;
    }

    /**
     * Adds multiple services.
     *
     * @return string
     */
    private function addServices()
    {
        $publicServices = $privateServices = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if ($definition->isSynthetic() || ($this->asFiles && $definition->isShared())) {
                continue;
            }
            if ($definition->isPublic()) {
                $publicServices .= $this->addService($id, $definition);
            } else {
                $privateServices .= $this->addService($id, $definition);
            }
        }

        return $publicServices.$privateServices;
    }

    private function generateServiceFiles()
    {
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->isSynthetic() && $definition->isShared()) {
                $code = $this->addService($id, $definition, $file);
                yield $file => $code;
            }
        }
    }

    private function addNewInstance(Definition $definition, $return, $instantiation, $id)
    {
        $class = $this->dumpValue($definition->getClass());

        $arguments = array();
        foreach ($definition->getArguments() as $value) {
            $arguments[] = $this->dumpValue($value);
        }

        if (null !== $definition->getFactory()) {
            $callable = $definition->getFactory();
            if (is_array($callable)) {
                if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $callable[1])) {
                    throw new RuntimeException(sprintf('Cannot dump definition because of invalid factory method (%s)', $callable[1] ?: 'n/a'));
                }

                if ($callable[0] instanceof Reference
                    || ($callable[0] instanceof Definition && $this->definitionVariables->contains($callable[0]))) {
                    return sprintf("        $return{$instantiation}%s->%s(%s);\n", $this->dumpValue($callable[0]), $callable[1], $arguments ? implode(', ', $arguments) : '');
                }

                $class = $this->dumpValue($callable[0]);
                // If the class is a string we can optimize call_user_func away
                if (0 === strpos($class, "'") && false === strpos($class, '$')) {
                    if ("''" === $class) {
                        throw new RuntimeException(sprintf('Cannot dump definition: The "%s" service is defined to be created by a factory but is missing the service reference, did you forget to define the factory service id or class?', $id));
                    }

                    return sprintf("        $return{$instantiation}%s::%s(%s);\n", $this->dumpLiteralClass($class), $callable[1], $arguments ? implode(', ', $arguments) : '');
                }

                if (0 === strpos($class, 'new ')) {
                    return sprintf("        $return{$instantiation}(%s)->%s(%s);\n", $this->dumpValue($callable[0]), $callable[1], $arguments ? implode(', ', $arguments) : '');
                }

                return sprintf("        $return{$instantiation}call_user_func(array(%s, '%s')%s);\n", $this->dumpValue($callable[0]), $callable[1], $arguments ? ', '.implode(', ', $arguments) : '');
            }

            return sprintf("        $return{$instantiation}%s(%s);\n", $this->dumpLiteralClass($this->dumpValue($callable)), $arguments ? implode(', ', $arguments) : '');
        }

        if (false !== strpos($class, '$')) {
            return sprintf("        \$class = %s;\n\n        $return{$instantiation}new \$class(%s);\n", $class, implode(', ', $arguments));
        }

        return sprintf("        $return{$instantiation}new %s(%s);\n", $this->dumpLiteralClass($class), implode(', ', $arguments));
    }

    /**
     * Adds the class headers.
     *
     * @param string $class     Class name
     * @param string $baseClass The name of the base class
     *
     * @return string
     */
    private function startClass($class, $baseClass)
    {
        $bagClass = $this->container->isCompiled() ? 'use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;' : 'use Symfony\Component\DependencyInjection\ParameterBag\\ParameterBag;';
        $namespaceLine = $this->namespace ? "\nnamespace {$this->namespace};\n" : '';

        $code = <<<EOF
<?php
$namespaceLine
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
$bagClass

/*{$this->docStar}
 * $class.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class $class extends $baseClass
{
    private \$parameters;
    private \$targetDirs = array();

    /*{$this->docStar}
     * Constructor.
     */
    public function __construct()
    {

EOF;
        if (null !== $this->targetDirRegex) {
            $dir = $this->asFiles ? '$this->targetDirs[0] = dirname(__DIR__)' : '__DIR__';
            $code .= <<<EOF
        \$dir = {$dir};
        for (\$i = 1; \$i <= {$this->targetDirMaxMatches}; ++\$i) {
            \$this->targetDirs[\$i] = \$dir = dirname(\$dir);
        }

EOF;
        }

        if ($this->container->isCompiled()) {
            if ($this->container->getParameterBag()->all()) {
                $code .= "        \$this->parameters = \$this->getDefaultParameters();\n\n";
            }
            $code .= "        \$this->services = array();\n";
        } else {
            $arguments = $this->container->getParameterBag()->all() ? 'new ParameterBag($this->getDefaultParameters())' : null;
            $code .= "        parent::__construct($arguments);\n";
        }

        $code .= $this->addNormalizedIds();
        $code .= $this->addMethodMap();
        $code .= $this->asFiles ? $this->addFileMap() : '';
        $code .= $this->addPrivateServices();
        $code .= $this->addAliases();
        $code .= <<<'EOF'
    }

EOF;
        if ($this->container->isCompiled()) {
            $code .= <<<EOF

    /*{$this->docStar}
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    /*{$this->docStar}
     * {@inheritdoc}
     */
    public function isCompiled()
    {
        return true;
    }

    /*{$this->docStar}
     * {@inheritdoc}
     */
    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

EOF;
        }

        if ($this->asFiles) {
            $code .= <<<EOF

    /*{$this->docStar}
     * {@inheritdoc}
     */
    protected function load(\$file, \$lazyLoad = true)
    {
        return require \$file;
    }

EOF;
        }

        $proxyDumper = $this->getProxyDumper();
        foreach ($this->container->getDefinitions() as $definition) {
            if (!$proxyDumper->isProxyCandidate($definition)) {
                continue;
            }
            if ($this->asFiles) {
                $proxyLoader = '$this->load(__DIR__."/{$class}.php")';
            } elseif ($this->namespace) {
                $proxyLoader = 'class_alias("'.$this->namespace.'\\\\{$class}", $class, false)';
            } else {
                $proxyLoader = '';
            }
            if ($proxyLoader) {
                $proxyLoader = "class_exists(\$class, false) || {$proxyLoader};\n\n        ";
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

    /**
     * Adds the normalizedIds property definition.
     *
     * @return string
     */
    private function addNormalizedIds()
    {
        $code = '';
        $normalizedIds = $this->container->getNormalizedIds();
        ksort($normalizedIds);
        foreach ($normalizedIds as $id => $normalizedId) {
            if ($this->container->has($normalizedId)) {
                $code .= '            '.$this->export($id).' => '.$this->export($normalizedId).",\n";
            }
        }

        return $code ? "        \$this->normalizedIds = array(\n".$code."        );\n" : '';
    }

    /**
     * Adds the methodMap property definition.
     *
     * @return string
     */
    private function addMethodMap()
    {
        $code = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->isSynthetic() && (!$this->asFiles || !$definition->isShared())) {
                $code .= '            '.$this->export($id).' => '.$this->export($this->generateMethodName($id)).",\n";
            }
        }

        return $code ? "        \$this->methodMap = array(\n{$code}        );\n" : '';
    }

    /**
     * Adds the fileMap property definition.
     *
     * @return string
     */
    private function addFileMap()
    {
        $code = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->isSynthetic() && $definition->isShared()) {
                $code .= sprintf("            %s => __DIR__.'/%s.php',\n", $this->export($id), $this->generateMethodName($id));
            }
        }

        return $code ? "        \$this->fileMap = array(\n{$code}        );\n" : '';
    }

    /**
     * Adds the privates property definition.
     *
     * @return string
     */
    private function addPrivateServices()
    {
        if (!$definitions = $this->container->getDefinitions()) {
            return '';
        }

        $code = '';
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->isPublic()) {
                $code .= '            '.$this->export($id)." => true,\n";
            }
        }

        if (empty($code)) {
            return '';
        }

        $out = "        \$this->privates = array(\n";
        $out .= $code;
        $out .= "        );\n";

        return $out;
    }

    /**
     * Adds the aliases property definition.
     *
     * @return string
     */
    private function addAliases()
    {
        if (!$aliases = $this->container->getAliases()) {
            return $this->container->isCompiled() ? "\n        \$this->aliases = array();\n" : '';
        }

        $code = "        \$this->aliases = array(\n";
        ksort($aliases);
        foreach ($aliases as $alias => $id) {
            $id = (string) $id;
            while (isset($aliases[$id])) {
                $id = (string) $aliases[$id];
            }
            $code .= '            '.$this->export($alias).' => '.$this->export($id).",\n";
        }

        return $code."        );\n";
    }

    /**
     * Adds default parameters method.
     *
     * @return string
     */
    private function addDefaultParametersMethod()
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }

        $php = array();
        $dynamicPhp = array();
        $normalizedParams = array();

        foreach ($this->container->getParameterBag()->all() as $key => $value) {
            if ($key !== $resolvedKey = $this->container->resolveEnvPlaceholders($key)) {
                throw new InvalidArgumentException(sprintf('Parameter name cannot use env parameters: %s.', $resolvedKey));
            }
            if ($key !== $lcKey = strtolower($key)) {
                $normalizedParams[] = sprintf('        %s => %s,', $this->export($lcKey), $this->export($key));
            }
            $export = $this->exportParameters(array($value));
            $export = explode('0 => ', substr(rtrim($export, " )\n"), 7, -1), 2);

            if (preg_match("/\\\$this->(?:getEnv\('\w++'\)|targetDirs\[\d++\])/", $export[1])) {
                $dynamicPhp[$key] = sprintf('%scase %s: $value = %s; break;', $export[0], $this->export($key), $export[1]);
            } else {
                $php[] = sprintf('%s%s => %s,', $export[0], $this->export($key), $export[1]);
            }
        }
        $parameters = sprintf("array(\n%s\n%s)", implode("\n", $php), str_repeat(' ', 8));

        $code = '';
        if ($this->container->isCompiled()) {
            $code .= <<<'EOF'

    /**
     * {@inheritdoc}
     */
    public function getParameter($name)
    {
        if (!(isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || array_key_exists($name, $this->parameters))) {
            $name = $this->normalizeParameterName($name);

            if (!(isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || array_key_exists($name, $this->parameters))) {
                throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
            }
        }
        if (isset($this->loadedDynamicParameters[$name])) {
            return $this->loadedDynamicParameters[$name] ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($name)
    {
        $name = $this->normalizeParameterName($name);

        return isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || array_key_exists($name, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($name, $value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterBag()
    {
        if (null === $this->parameterBag) {
            $parameters = $this->parameters;
            foreach ($this->loadedDynamicParameters as $name => $loaded) {
                $parameters[$name] = $loaded ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
            }
            $this->parameterBag = new FrozenParameterBag($parameters);
        }

        return $this->parameterBag;
    }

EOF;
            if ('' === $this->docStar) {
                $code = str_replace('/**', '/*', $code);
            }

            if ($dynamicPhp) {
                $loadedDynamicParameters = $this->exportParameters(array_combine(array_keys($dynamicPhp), array_fill(0, count($dynamicPhp), false)), '', 8);
                $getDynamicParameter = <<<'EOF'
        switch ($name) {
%s
            default: throw new InvalidArgumentException(sprintf('The dynamic parameter "%%s" must be defined.', $name));
        }
        $this->loadedDynamicParameters[$name] = true;

        return $this->dynamicParameters[$name] = $value;
EOF;
                $getDynamicParameter = sprintf($getDynamicParameter, implode("\n", $dynamicPhp));
            } else {
                $loadedDynamicParameters = 'array()';
                $getDynamicParameter = str_repeat(' ', 8).'throw new InvalidArgumentException(sprintf(\'The dynamic parameter "%s" must be defined.\', $name));';
            }

            $code .= <<<EOF

    private \$loadedDynamicParameters = {$loadedDynamicParameters};
    private \$dynamicParameters = array();

    /*{$this->docStar}
     * Computes a dynamic parameter.
     *
     * @param string The name of the dynamic parameter to load
     *
     * @return mixed The value of the dynamic parameter
     *
     * @throws InvalidArgumentException When the dynamic parameter does not exist
     */
    private function getDynamicParameter(\$name)
    {
{$getDynamicParameter}
    }


EOF;

            $code .= '    private $normalizedParameterNames = '.($normalizedParams ? sprintf("array(\n%s\n    );", implode("\n", $normalizedParams)) : 'array();')."\n";
            $code .= <<<'EOF'

    private function normalizeParameterName($name)
    {
        if (isset($this->normalizedParameterNames[$normalizedName = strtolower($name)]) || isset($this->parameters[$normalizedName]) || array_key_exists($normalizedName, $this->parameters)) {
            $normalizedName = isset($this->normalizedParameterNames[$normalizedName]) ? $this->normalizedParameterNames[$normalizedName] : $normalizedName;
            if ((string) $name !== $normalizedName) {
                @trigger_error(sprintf('Parameter names will be made case sensitive in Symfony 4.0. Using "%s" instead of "%s" is deprecated since version 3.4.', $name, $normalizedName), E_USER_DEPRECATED);
            }
        } else {
            $normalizedName = $this->normalizedParameterNames[$normalizedName] = (string) $name;
        }

        return $normalizedName;
    }

EOF;
        } elseif ($dynamicPhp) {
            throw new RuntimeException('You cannot dump a not-frozen container with dynamic parameters.');
        }

        $code .= <<<EOF

    /*{$this->docStar}
     * Gets the default parameters.
     *
     * @return array An array of the default parameters
     */
    protected function getDefaultParameters()
    {
        return $parameters;
    }

EOF;

        return $code;
    }

    /**
     * Exports parameters.
     *
     * @param array  $parameters
     * @param string $path
     * @param int    $indent
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function exportParameters(array $parameters, $path = '', $indent = 12)
    {
        $php = array();
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = $this->exportParameters($value, $path.'/'.$key, $indent + 4);
            } elseif ($value instanceof ArgumentInterface) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain special arguments. "%s" found in "%s".', get_class($value), $path.'/'.$key));
            } elseif ($value instanceof Variable) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain variable references. Variable "%s" found in "%s".', $value, $path.'/'.$key));
            } elseif ($value instanceof Definition) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain service definitions. Definition for "%s" found in "%s".', $value->getClass(), $path.'/'.$key));
            } elseif ($value instanceof Reference) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain references to other services (reference to service "%s" found in "%s").', $value, $path.'/'.$key));
            } elseif ($value instanceof Expression) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain expressions. Expression "%s" found in "%s".', $value, $path.'/'.$key));
            } else {
                $value = $this->export($value);
            }

            $php[] = sprintf('%s%s => %s,', str_repeat(' ', $indent), $this->export($key), $value);
        }

        return sprintf("array(\n%s\n%s)", implode("\n", $php), str_repeat(' ', $indent - 4));
    }

    /**
     * Ends the class definition.
     *
     * @return string
     */
    private function endClass()
    {
        return <<<'EOF'
}

EOF;
    }

    /**
     * Wraps the service conditionals.
     *
     * @param string $value
     * @param string $code
     *
     * @return string
     */
    private function wrapServiceConditionals($value, $code)
    {
        if (!$condition = $this->getServiceConditionals($value)) {
            return $code;
        }

        // re-indent the wrapped code
        $code = implode("\n", array_map(function ($line) { return $line ? '    '.$line : $line; }, explode("\n", $code)));

        return sprintf("        if (%s) {\n%s        }\n", $condition, $code);
    }

    /**
     * Get the conditions to execute for conditional services.
     *
     * @param string $value
     *
     * @return null|string
     */
    private function getServiceConditionals($value)
    {
        $conditions = array();
        foreach (ContainerBuilder::getInitializedConditionals($value) as $service) {
            if (!$this->container->hasDefinition($service)) {
                return 'false';
            }
            $conditions[] = sprintf("isset(\$this->services['%s'])", $service);
        }
        foreach (ContainerBuilder::getServiceConditionals($value) as $service) {
            if ($this->container->hasDefinition($service) && !$this->container->getDefinition($service)->isPublic()) {
                continue;
            }

            $conditions[] = sprintf("\$this->has('%s')", $service);
        }

        if (!$conditions) {
            return '';
        }

        return implode(' && ', $conditions);
    }

    /**
     * Builds service calls from arguments.
     *
     * @param array $arguments
     * @param array &$calls    By reference
     * @param array &$behavior By reference
     */
    private function getServiceCallsFromArguments(array $arguments, array &$calls, array &$behavior)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $this->getServiceCallsFromArguments($argument, $calls, $behavior);
            } elseif ($argument instanceof Reference) {
                $id = (string) $argument;

                if (!isset($calls[$id])) {
                    $calls[$id] = 0;
                }
                if (!isset($behavior[$id])) {
                    $behavior[$id] = $argument->getInvalidBehavior();
                } else {
                    $behavior[$id] = min($behavior[$id], $argument->getInvalidBehavior());
                }

                ++$calls[$id];
            }
        }
    }

    /**
     * Returns the inline definition.
     *
     * @param Definition $definition
     *
     * @return array
     */
    private function getInlinedDefinitions(Definition $definition)
    {
        if (false === $this->inlinedDefinitions->contains($definition)) {
            $definitions = array_merge(
                $this->getDefinitionsFromArguments($definition->getArguments()),
                $this->getDefinitionsFromArguments($definition->getMethodCalls()),
                $this->getDefinitionsFromArguments($definition->getProperties()),
                $this->getDefinitionsFromArguments(array($definition->getConfigurator())),
                $this->getDefinitionsFromArguments(array($definition->getFactory()))
            );

            $this->inlinedDefinitions->offsetSet($definition, $definitions);

            return $definitions;
        }

        return $this->inlinedDefinitions->offsetGet($definition);
    }

    /**
     * Gets the definition from arguments.
     *
     * @param array $arguments
     *
     * @return array
     */
    private function getDefinitionsFromArguments(array $arguments)
    {
        $definitions = array();
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $definitions = array_merge($definitions, $this->getDefinitionsFromArguments($argument));
            } elseif ($argument instanceof Definition) {
                $definitions = array_merge(
                    $definitions,
                    $this->getInlinedDefinitions($argument),
                    array($argument)
                );
            }
        }

        return $definitions;
    }

    /**
     * Checks if a service id has a reference.
     *
     * @param string $id
     * @param array  $arguments
     * @param bool   $deep
     * @param array  $visited
     *
     * @return bool
     */
    private function hasReference($id, array $arguments, $deep = false, array &$visited = array())
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                if ($this->hasReference($id, $argument, $deep, $visited)) {
                    return true;
                }
            } elseif ($argument instanceof Reference) {
                $argumentId = (string) $argument;
                if ($id === $argumentId) {
                    return true;
                }

                if ($deep && !isset($visited[$argumentId]) && 'service_container' !== $argumentId) {
                    $visited[$argumentId] = true;

                    $service = $this->container->getDefinition($argumentId);

                    // if the proxy manager is enabled, disable searching for references in lazy services,
                    // as these services will be instantiated lazily and don't have direct related references.
                    if ($service->isLazy() && !$this->getProxyDumper() instanceof NullDumper) {
                        continue;
                    }

                    $arguments = array_merge($service->getMethodCalls(), $service->getArguments(), $service->getProperties());

                    if ($this->hasReference($id, $arguments, $deep, $visited)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Dumps values.
     *
     * @param mixed $value
     * @param bool  $interpolate
     *
     * @return string
     *
     * @throws RuntimeException
     */
    private function dumpValue($value, $interpolate = true)
    {
        if (is_array($value)) {
            if ($value && $interpolate && false !== $param = array_search($value, $this->container->getParameterBag()->all(), true)) {
                return $this->dumpValue("%$param%");
            }
            $code = array();
            foreach ($value as $k => $v) {
                $code[] = sprintf('%s => %s', $this->dumpValue($k, $interpolate), $this->dumpValue($v, $interpolate));
            }

            return sprintf('array(%s)', implode(', ', $code));
        } elseif ($value instanceof ArgumentInterface) {
            $scope = array($this->definitionVariables, $this->referenceVariables, $this->variableCount);
            $this->definitionVariables = $this->referenceVariables = null;

            try {
                if ($value instanceof ServiceClosureArgument) {
                    $value = $value->getValues()[0];
                    $code = $this->dumpValue($value, $interpolate);

                    if ($value instanceof TypedReference) {
                        $code = sprintf('$f = function (\\%s $v%s) { return $v; }; return $f(%s);', $value->getType(), ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $value->getInvalidBehavior() ? ' = null' : '', $code);
                    } else {
                        $code = sprintf('return %s;', $code);
                    }

                    return sprintf("function () {\n            %s\n        }", $code);
                }

                if ($value instanceof IteratorArgument) {
                    $operands = array(0);
                    $code = array();
                    $code[] = 'new RewindableGenerator(function () {';

                    if (!$values = $value->getValues()) {
                        $code[] = '            return new \EmptyIterator();';
                    } else {
                        $countCode = array();
                        $countCode[] = 'function () {';

                        foreach ($values as $k => $v) {
                            ($c = $this->getServiceConditionals($v)) ? $operands[] = "(int) ($c)" : ++$operands[0];
                            $v = $this->wrapServiceConditionals($v, sprintf("        yield %s => %s;\n", $this->dumpValue($k, $interpolate), $this->dumpValue($v, $interpolate)));
                            foreach (explode("\n", $v) as $v) {
                                if ($v) {
                                    $code[] = '    '.$v;
                                }
                            }
                        }

                        $countCode[] = sprintf('            return %s;', implode(' + ', $operands));
                        $countCode[] = '        }';
                    }

                    $code[] = sprintf('        }, %s)', count($operands) > 1 ? implode("\n", $countCode) : $operands[0]);

                    return implode("\n", $code);
                }
            } finally {
                list($this->definitionVariables, $this->referenceVariables, $this->variableCount) = $scope;
            }
        } elseif ($value instanceof Definition) {
            if (null !== $this->definitionVariables && $this->definitionVariables->contains($value)) {
                return $this->dumpValue($this->definitionVariables->offsetGet($value), $interpolate);
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

            $arguments = array();
            foreach ($value->getArguments() as $argument) {
                $arguments[] = $this->dumpValue($argument);
            }

            if (null !== $value->getFactory()) {
                $factory = $value->getFactory();

                if (is_string($factory)) {
                    return sprintf('%s(%s)', $this->dumpLiteralClass($this->dumpValue($factory)), implode(', ', $arguments));
                }

                if (is_array($factory)) {
                    if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $factory[1])) {
                        throw new RuntimeException(sprintf('Cannot dump definition because of invalid factory method (%s)', $factory[1] ?: 'n/a'));
                    }

                    if (is_string($factory[0])) {
                        return sprintf('%s::%s(%s)', $this->dumpLiteralClass($this->dumpValue($factory[0])), $factory[1], implode(', ', $arguments));
                    }

                    if ($factory[0] instanceof Definition) {
                        return sprintf("call_user_func(array(%s, '%s')%s)", $this->dumpValue($factory[0]), $factory[1], count($arguments) > 0 ? ', '.implode(', ', $arguments) : '');
                    }

                    if ($factory[0] instanceof Reference) {
                        return sprintf('%s->%s(%s)', $this->dumpValue($factory[0]), $factory[1], implode(', ', $arguments));
                    }
                }

                throw new RuntimeException('Cannot dump definition because of invalid factory');
            }

            $class = $value->getClass();
            if (null === $class) {
                throw new RuntimeException('Cannot dump definitions which have no class nor factory.');
            }

            return sprintf('new %s(%s)', $this->dumpLiteralClass($this->dumpValue($class)), implode(', ', $arguments));
        } elseif ($value instanceof Variable) {
            return '$'.$value;
        } elseif ($value instanceof Reference) {
            if (null !== $this->referenceVariables && isset($this->referenceVariables[$id = (string) $value])) {
                return $this->dumpValue($this->referenceVariables[$id], $interpolate);
            }

            return $this->getServiceCall((string) $value, $value);
        } elseif ($value instanceof Expression) {
            return $this->getExpressionLanguage()->compile((string) $value, array('this' => 'container'));
        } elseif ($value instanceof Parameter) {
            return $this->dumpParameter($value);
        } elseif (true === $interpolate && is_string($value)) {
            if (preg_match('/^%([^%]+)%$/', $value, $match)) {
                // we do this to deal with non string values (Boolean, integer, ...)
                // the preg_replace_callback converts them to strings
                return $this->dumpParameter($match[1]);
            } else {
                $replaceParameters = function ($match) {
                    return "'.".$this->dumpParameter($match[2]).".'";
                };

                $code = str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replaceParameters, $this->export($value)));

                return $code;
            }
        } elseif (is_object($value) || is_resource($value)) {
            throw new RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
        }

        return $this->export($value);
    }

    /**
     * Dumps a string to a literal (aka PHP Code) class value.
     *
     * @param string $class
     *
     * @return string
     *
     * @throws RuntimeException
     */
    private function dumpLiteralClass($class)
    {
        if (false !== strpos($class, '$')) {
            return sprintf('${($_ = %s) && false ?: "_"}', $class);
        }
        if (0 !== strpos($class, "'") || !preg_match('/^\'(?:\\\{2})?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new RuntimeException(sprintf('Cannot dump definition because of invalid class name (%s)', $class ?: 'n/a'));
        }

        $class = substr(str_replace('\\\\', '\\', $class), 1, -1);

        return 0 === strpos($class, '\\') ? $class : '\\'.$class;
    }

    /**
     * Dumps a parameter.
     *
     * @param string $name
     *
     * @return string
     */
    private function dumpParameter($name)
    {
        if ($this->container->isCompiled() && $this->container->hasParameter($name)) {
            $value = $this->container->getParameter($name);
            $dumpedValue = $this->dumpValue($value, false);

            if (!$value || !is_array($value)) {
                return $dumpedValue;
            }

            if (!preg_match("/\\\$this->(?:getEnv\('\w++'\)|targetDirs\[\d++\])/", $dumpedValue)) {
                return sprintf("\$this->parameters['%s']", $name);
            }
        }

        return sprintf("\$this->getParameter('%s')", $name);
    }

    /**
     * Gets a service call.
     *
     * @param string    $id
     * @param Reference $reference
     *
     * @return string
     */
    private function getServiceCall($id, Reference $reference = null)
    {
        while ($this->container->hasAlias($id)) {
            $id = (string) $this->container->getAlias($id);
        }

        if ('service_container' === $id) {
            return '$this';
        }

        if (null !== $reference && ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE === $reference->getInvalidBehavior()) {
            $code = 'null';
        } elseif ($this->container->hasDefinition($id)) {
            $definition = $this->container->getDefinition($id);

            if ($this->isTrivialInstance($definition)) {
                $code = substr($this->addNewInstance($definition, '', '', $id), 8, -2);
                if ($definition->isShared()) {
                    $code = sprintf('$this->services[\'%s\'] = %s', $id, $code);
                }
            } elseif ($this->asFiles && $definition->isShared()) {
                $code = sprintf("\$this->load(__DIR__.'/%s.php')", $this->generateMethodName($id));
            } else {
                $code = sprintf('$this->%s()', $this->generateMethodName($id));
            }
        } elseif (null !== $reference && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $reference->getInvalidBehavior()) {
            $code = sprintf('$this->get(\'%s\', ContainerInterface::NULL_ON_INVALID_REFERENCE)', $id);
        } else {
            $code = sprintf('$this->get(\'%s\')', $id);
        }

        // The following is PHP 5.5 syntax for what could be written as "(\$this->services['$id'] ?? $code)" on PHP>=7.0

        return "\${(\$_ = isset(\$this->services['$id']) ? \$this->services['$id'] : $code) && false ?: '_'}";
    }

    /**
     * Initializes the method names map to avoid conflicts with the Container methods.
     *
     * @param string $class the container base class
     */
    private function initializeMethodNamesMap($class)
    {
        $this->serviceIdToMethodNameMap = array();
        $this->usedMethodNames = array();

        if ($reflectionClass = $this->container->getReflectionClass($class)) {
            foreach ($reflectionClass->getMethods() as $method) {
                $this->usedMethodNames[strtolower($method->getName())] = true;
            }
        }
    }

    /**
     * Convert a service id to a valid PHP method name.
     *
     * @param string $id
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function generateMethodName($id)
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

    /**
     * Returns the next name to use.
     *
     * @return string
     */
    private function getNextVariableName()
    {
        $firstChars = self::FIRST_CHARS;
        $firstCharsLength = strlen($firstChars);
        $nonFirstChars = self::NON_FIRST_CHARS;
        $nonFirstCharsLength = strlen($nonFirstChars);

        while (true) {
            $name = '';
            $i = $this->variableCount;

            if ('' === $name) {
                $name .= $firstChars[$i % $firstCharsLength];
                $i = (int) ($i / $firstCharsLength);
            }

            while ($i > 0) {
                --$i;
                $name .= $nonFirstChars[$i % $nonFirstCharsLength];
                $i = (int) ($i / $nonFirstCharsLength);
            }

            ++$this->variableCount;

            // check that the name is not reserved
            if (in_array($name, $this->reservedVariables, true)) {
                continue;
            }

            return $name;
        }
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $providers = $this->container->getExpressionLanguageProviders();
            $this->expressionLanguage = new ExpressionLanguage(null, $providers, function ($arg) {
                $id = '""' === substr_replace($arg, '', 1, -1) ? stripcslashes(substr($arg, 1, -1)) : null;

                if (null !== $id && ($this->container->hasAlias($id) || $this->container->hasDefinition($id))) {
                    return $this->getServiceCall($id);
                }

                return sprintf('$this->get(%s)', $arg);
            });

            if ($this->container->isTrackingResources()) {
                foreach ($providers as $provider) {
                    $this->container->addObjectResource($provider);
                }
            }
        }

        return $this->expressionLanguage;
    }

    private function export($value)
    {
        if (null !== $this->targetDirRegex && is_string($value) && preg_match($this->targetDirRegex, $value, $matches, PREG_OFFSET_CAPTURE)) {
            $prefix = $matches[0][1] ? $this->doExport(substr($value, 0, $matches[0][1])).'.' : '';
            $suffix = $matches[0][1] + strlen($matches[0][0]);
            $suffix = isset($value[$suffix]) ? '.'.$this->doExport(substr($value, $suffix)) : '';
            $dirname = '__DIR__';
            $offset = 1 + $this->targetDirMaxMatches - count($matches);

            if ($this->asFiles || 0 < $offset) {
                $dirname = sprintf('$this->targetDirs[%d]', $offset);
            }

            if ($prefix || $suffix) {
                return sprintf('(%s%s%s)', $prefix, $dirname, $suffix);
            }

            return $dirname;
        }

        return $this->doExport($value);
    }

    private function doExport($value)
    {
        $export = var_export($value, true);

        if ("'" === $export[0] && $export !== $resolvedExport = $this->container->resolveEnvPlaceholders($export, "'.\$this->getEnv('%s').'")) {
            $export = $resolvedExport;
            if ("'" === $export[1]) {
                $export = substr($export, 3);
            }
            if (".''" === substr($export, -3)) {
                $export = substr($export, 0, -3);
            }
        }

        return $export;
    }
}
