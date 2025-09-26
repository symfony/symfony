<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Builder;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\Builder\ExprBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Config\Definition\VariableNode;
use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Resource\ImportsTrait;
use Symfony\Component\DependencyInjection\Resource\ParametersTrait;
use Symfony\Component\DependencyInjection\Resource\ServicesTrait;

/**
 * Generate ConfigBuilders to help create valid config.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ConfigBuilderGenerator implements ConfigBuilderGeneratorInterface, ConfigClassAwareBuilderGeneratorInterface
{
    /**
     * @var ClassBuilder[]
     */
    private array $classes = [];

    public function __construct(
        private string $outputDir,
    ) {
    }

    /**
     * @return \Closure that will return the root config class
     */
    public function build(ConfigurationInterface $configuration): \Closure
    {
        $this->classes = [];

        $rootNode = $configuration->getConfigTreeBuilder()->buildTree();
        $rootClass = new ClassBuilder('Symfony\\Config', $rootNode->getName());

        $path = $this->getFullPath($rootClass);
        if (!is_file($path)) {
            // Generate the class if the file doesn't exist
            $this->buildNode($rootNode, $rootClass, $this->getSubNamespace($rootClass));
            $this->buildConfigureOption($rootNode, $rootClass);
            $rootClass->addImplements(ConfigBuilderInterface::class);
            $rootClass->addMethod('getExtensionAlias', '
public function NAME(): string
{
    return \'ALIAS\';
}', ['ALIAS' => $rootNode->getPath()]);

            $this->writeRootClass($rootClass);
            $this->writeClasses();
        }

        return function () use ($path, $rootClass) {
            require_once $path;
            $className = $rootClass->getFqcn();

            return new $className();
        };
    }

    /**
     * @param array<Configuration> $configurations
     */
    public function buildConfigClassAndTraits(array $configurations): \Closure
    {
        $traitsClosure = (new ConfigTraitsGenerator($this->outputDir))->build($configurations);

        $class = new ClassBuilder('Symfony\\Config', '');
        $class->addTrait(ImportsTrait::class);
        $class->addTrait(ParametersTrait::class);
        $class->addTrait(ServicesTrait::class);
        $class->addUse(ContainerConfigurator::class);

        $class->addProperty('builders', 'array');

        foreach ($configurations as $alias => $configuration) {
            $class->addUse('Symfony\\Config\\'.ucfirst($this->camelCase($alias)).'Config');
            $class->addTrait('Symfony\\Config\\'.ucfirst($this->camelCase($alias)).'Trait');
        }

        $configurationKeys = array_keys($configurations);

        foreach ($configurationKeys as $configurationKey) {
            $camelCaseKey = $this->camelCase($configurationKey);

            $class->addProperty(\sprintf('%sConfig', $camelCaseKey), \sprintf('%sConfig', ucfirst($camelCaseKey)));
        }

        $class->addMethod('__construct', <<<'PHP'
            public function __construct(public readonly ?string $env)
            {
            CONFIGS
                $this->builders = [BUILDERS];
            }
            PHP, [
            'BUILDERS' => implode(', ', array_map(fn ($alias) => \sprintf('$this->%sConfig', $this->camelCase($alias)), $configurationKeys)),
            'CONFIGS' => implode("\n", array_map(fn ($alias) => \sprintf('    $this->%sConfig = new %sConfig();', $this->camelCase($alias), ucfirst($this->camelCase($alias))), $configurationKeys)),
        ]);

        $class->addMethod('getBuilders', <<<'PHP'
            public function getBuilders(): array
            {
                return $this->builders;
            }
            PHP);

        $path = $this->getFullPath($class);
        file_put_contents($path, $class->build());

        return function () use ($path, $traitsClosure) {
            $traitsClosure();

            return require_once $path;
        };
    }

    private function camelCase(string $input): string
    {
        $output = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));

        return preg_replace('#\W#', '', $output);
    }

    private function getFullPath(ClassBuilder $class): string
    {
        $directory = $this->outputDir.\DIRECTORY_SEPARATOR.$class->getDirectory();
        if (!is_dir($directory)) {
            @mkdir($directory, 0o777, true);
        }

        return $directory.\DIRECTORY_SEPARATOR.$class->getFilename();
    }

    private function writeRootClass(ClassBuilder $rootClass): void
    {
        $this->buildConstructor($rootClass);
        $this->buildToArray($rootClass, true);
        if ($rootClass->getProperties()) {
            $rootClass->addProperty('_usedProperties', null, '[]');
        }
        $this->buildSetExtraKey($rootClass);

        file_put_contents($this->getFullPath($rootClass), $rootClass->build());
    }

    private function writeClasses(): void
    {
        foreach ($this->classes as $class) {
            $this->buildConstructor($class);
            $this->buildToArray($class);
            if ($class->getProperties()) {
                $class->addProperty('_usedProperties', null, '[]');
            }
            $this->buildSetExtraKey($class);

            file_put_contents($this->getFullPath($class), $class->build());
        }

        $this->classes = [];
    }

    private function buildConfigureOption(ArrayNode $node, ClassBuilder $class): void
    {
        $class->addProperty('configOutput', defaultValue: '[]');

        $body = '
public function NAME(
    /** @var PHPDOC_ARRAY_SHAPE $config */
    PARAM_TYPE $config = []): void
{
    $this->configOutput = $config;
}';

        $class->addMethod('configure', $body, [
            'PARAM_TYPE' => 'array',
            'PHPDOC_ARRAY_SHAPE' => str_replace("\n", "\n    ", ArrayShapeGenerator::generate($node)),
        ]);
    }

    private function buildNode(NodeInterface $node, ClassBuilder $class, string $namespace): void
    {
        if (!$node instanceof ArrayNode) {
            throw new \LogicException('The node was expected to be an ArrayNode. This Configuration includes an edge case not supported yet.');
        }

        foreach ($node->getChildren() as $child) {
            match (true) {
                $child instanceof ScalarNode => $this->handleScalarNode($child, $class),
                $child instanceof PrototypedArrayNode => $this->handlePrototypedArrayNode($child, $class, $namespace),
                $child instanceof VariableNode => $this->handleVariableNode($child, $class),
                $child instanceof ArrayNode => $this->handleArrayNode($child, $class, $namespace),
                default => throw new \RuntimeException(\sprintf('Unknown node "%s".', $child::class)),
            };
        }
    }

    private function handleArrayNode(ArrayNode $node, ClassBuilder $class, string $namespace): void
    {
        $childClass = new ClassBuilder($namespace, $node->getName());
        $childClass->setAllowExtraKeys($node->shouldIgnoreExtraKeys());
        $class->addRequire($childClass);
        $this->classes[] = $childClass;

        $nodeTypes = $this->getParameterTypes($node);
        $paramType = $this->getParamType($nodeTypes);

        $hasNormalizationClosures = $this->hasNormalizationClosures($node);
        $comment = $this->getComment($node);
        if ($hasNormalizationClosures && 'array' !== $paramType) {
            $comment = \sprintf(" * @template TValue of %s\n * @param TValue \$value\n%s", $paramType, $comment);
            $comment .= \sprintf(' * @return %s|$this'."\n", $childClass->getFqcn());
            $comment .= \sprintf(' * @psalm-return (TValue is array ? %s : static)'."\n ", $childClass->getFqcn());
        }
        if ('' !== $comment) {
            $comment = "/**\n$comment*/\n";
        }

        $property = $class->addProperty(
            $node->getName(),
            $this->getType($childClass->getFqcn(), $hasNormalizationClosures)
        );
        $body = $hasNormalizationClosures && 'array' !== $paramType ? '
COMMENTpublic function NAME(PARAM_TYPE $value = []): CLASS|static
{
    if (!\is_array($value)) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY = $value;

        return $this;
    }

    if (!$this->PROPERTY instanceof CLASS) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY = new CLASS($value);
    } elseif (0 < \func_num_args()) {
        throw new InvalidConfigurationException(\'The node created by "NAME()" has already been initialized. You cannot pass values the second time you call NAME().\');
    }

    return $this->PROPERTY;
}' : '
COMMENTpublic function NAME(array $value = []): CLASS
{
    if (null === $this->PROPERTY) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY = new CLASS($value);
    } elseif (0 < \func_num_args()) {
        throw new InvalidConfigurationException(\'The node created by "NAME()" has already been initialized. You cannot pass values the second time you call NAME().\');
    }

    return $this->PROPERTY;
}';
        $class->addUse(InvalidConfigurationException::class);
        $class->addMethod($node->getName(), $body, [
            'COMMENT' => $comment,
            'PROPERTY' => $property->getName(),
            'CLASS' => $childClass->getFqcn(),
            'PARAM_TYPE' => $paramType,
        ]);

        $this->buildNode($node, $childClass, $this->getSubNamespace($childClass));
    }

    private function handleVariableNode(VariableNode $node, ClassBuilder $class): void
    {
        $comment = $this->getComment($node);
        $property = $class->addProperty($node->getName());
        $class->addUse(ParamConfigurator::class);

        $body = '
/**
COMMENT *
 * @return $this
 */
public function NAME(mixed $valueDEFAULT): static
{
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY = $value;

    return $this;
}';
        $class->addMethod($node->getName(), $body, [
            'PROPERTY' => $property->getName(),
            'COMMENT' => $comment,
            'DEFAULT' => $node->hasDefaultValue() ? ' = '.var_export($node->getDefaultValue(), true) : '',
        ]);
    }

    private function handlePrototypedArrayNode(PrototypedArrayNode $node, ClassBuilder $class, string $namespace): void
    {
        $name = $this->getSingularName($node);
        $prototype = $node->getPrototype();
        $methodName = $name;
        $hasNormalizationClosures = $this->hasNormalizationClosures($node) || $this->hasNormalizationClosures($prototype);

        $nodeParameterTypes = $this->getParameterTypes($node);
        $prototypeParameterTypes = $this->getParameterTypes($prototype);
        $noKey = null === $key = $node->getKeyAttribute();
        if (!$prototype instanceof ArrayNode || ($prototype instanceof PrototypedArrayNode && $prototype->getPrototype() instanceof ScalarNode)) {
            $class->addUse(ParamConfigurator::class);
            $property = $class->addProperty($node->getName());
            if ($noKey) {
                // This is an array of values; don't use singular name
                $nodeTypesWithoutArray = array_filter($nodeParameterTypes, static fn ($type) => 'array' !== $type);
                $body = '
/**
 * @param ParamConfigurator|list<ParamConfigurator|PROTOTYPE_TYPE>EXTRA_TYPE $value
 *
 * @return $this
 */
public function NAME(PARAM_TYPE $value): static
{
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY = $value;

    return $this;
}';

                $class->addMethod($node->getName(), $body, [
                    'PROPERTY' => $property->getName(),
                    'PROTOTYPE_TYPE' => implode('|', $prototypeParameterTypes),
                    'EXTRA_TYPE' => $nodeTypesWithoutArray ? '|'.implode('|', $nodeTypesWithoutArray) : '',
                    'PARAM_TYPE' => $this->getParamType($nodeParameterTypes, true),
                ]);
            } else {
                $body = '
/**
 * @return $this
 */
public function NAME(string $VAR, TYPE $VALUE): static
{
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY[$VAR] = $VALUE;

    return $this;
}';

                $class->addMethod($methodName, $body, [
                    'PROPERTY' => $property->getName(),
                    'TYPE' => $this->getParamType($prototypeParameterTypes, true),
                    'VAR' => '' === $key ? 'key' : $key,
                    'VALUE' => 'value' === $key ? 'data' : 'value',
                ]);
            }

            return;
        }

        $childClass = new ClassBuilder($namespace, $name);
        if ($prototype instanceof ArrayNode) {
            $childClass->setAllowExtraKeys($prototype->shouldIgnoreExtraKeys());
        }
        $class->addRequire($childClass);
        $this->classes[] = $childClass;

        $property = $class->addProperty(
            $node->getName(),
            $this->getType($childClass->getFqcn().'[]', $hasNormalizationClosures)
        );

        $paramType = $this->getParamType($noKey ? $nodeParameterTypes : $prototypeParameterTypes);

        $comment = $this->getComment($node);
        if ($hasNormalizationClosures && 'array' !== $paramType) {
            $comment = \sprintf(" * @template TValue of %s\n * @param TValue \$value\n%s", $paramType, $comment);
            $comment .= \sprintf(' * @return %s|$this'."\n", $childClass->getFqcn());
            $comment .= \sprintf(' * @psalm-return (TValue is array ? %s : static)'."\n ", $childClass->getFqcn());
        }
        if ('' !== $comment) {
            $comment = "/**\n$comment*/\n";
        }

        if ($noKey) {
            $body = $hasNormalizationClosures && 'array' !== $paramType ? '
COMMENTpublic function NAME(PARAM_TYPE $value = []): CLASS|static
{
    $this->_usedProperties[\'PROPERTY\'] = true;
    if (!\is_array($value)) {
        $this->PROPERTY[] = $value;

        return $this;
    }

    return $this->PROPERTY[] = new CLASS($value);
}' : '
COMMENTpublic function NAME(array $value = []): CLASS
{
    $this->_usedProperties[\'PROPERTY\'] = true;

    return $this->PROPERTY[] = new CLASS($value);
}';
            $class->addMethod($methodName, $body, [
                'COMMENT' => $comment,
                'PROPERTY' => $property->getName(),
                'CLASS' => $childClass->getFqcn(),
                'PARAM_TYPE' => $paramType,
            ]);
        } else {
            $body = $hasNormalizationClosures && 'array' !== $paramType ? '
COMMENTpublic function NAME(string $VAR, PARAM_TYPE $VALUE = []): CLASS|static
{
    if (!\is_array($VALUE)) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY[$VAR] = $VALUE;

        return $this;
    }

    if (!isset($this->PROPERTY[$VAR]) || !$this->PROPERTY[$VAR] instanceof CLASS) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY[$VAR] = new CLASS($VALUE);
    } elseif (1 < \func_num_args()) {
        throw new InvalidConfigurationException(\'The node created by "NAME()" has already been initialized. You cannot pass values the second time you call NAME().\');
    }

    return $this->PROPERTY[$VAR];
}' : '
COMMENTpublic function NAME(string $VAR, array $VALUE = []): CLASS
{
    if (!isset($this->PROPERTY[$VAR])) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY[$VAR] = new CLASS($VALUE);
    } elseif (1 < \func_num_args()) {
        throw new InvalidConfigurationException(\'The node created by "NAME()" has already been initialized. You cannot pass values the second time you call NAME().\');
    }

    return $this->PROPERTY[$VAR];
}';
            $class->addUse(InvalidConfigurationException::class);
            $class->addMethod($methodName, str_replace('$value', '$VAR', $body), [
                'COMMENT' => $comment, 'PROPERTY' => $property->getName(),
                'CLASS' => $childClass->getFqcn(),
                'VAR' => '' === $key ? 'key' : $key,
                'VALUE' => 'value' === $key ? 'data' : 'value',
                'PARAM_TYPE' => $paramType,
            ]);
        }

        $this->buildNode($prototype, $childClass, $namespace.'\\'.$childClass->getName());
    }

    private function handleScalarNode(ScalarNode $node, ClassBuilder $class): void
    {
        $comment = $this->getComment($node);
        $property = $class->addProperty($node->getName());
        $class->addUse(ParamConfigurator::class);

        $body = '
/**
COMMENT * @return $this
 */
public function NAME($value): static
{
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY = $value;

    return $this;
}';

        $class->addMethod($node->getName(), $body, ['PROPERTY' => $property->getName(), 'COMMENT' => $comment]);
    }

    private function getParameterTypes(NodeInterface $node): array
    {
        $paramTypes = [];
        if ($node instanceof BaseNode) {
            foreach ($node->getNormalizedTypes() as $type) {
                $paramTypes[] = match ($type) {
                    ExprBuilder::TYPE_ANY => 'mixed',
                    ExprBuilder::TYPE_STRING => 'string',
                    ExprBuilder::TYPE_NULL => 'null',
                    ExprBuilder::TYPE_ARRAY => 'array',
                    ExprBuilder::TYPE_BOOL => 'bool',
                    ExprBuilder::TYPE_BACKED_ENUM => '\BackedEnum',
                    ExprBuilder::TYPE_INT => 'int',
                };
            }
        }

        if ($node instanceof BooleanNode) {
            $paramTypes[] = 'bool';
        } elseif ($node instanceof IntegerNode) {
            $paramTypes[] = 'int';
        } elseif ($node instanceof FloatNode) {
            $paramTypes[] = 'float';
        } elseif ($node instanceof EnumNode) {
            $paramTypes[] = 'mixed';
        } elseif ($node instanceof ArrayNode) {
            $paramTypes[] = 'array';
        } elseif ($node instanceof VariableNode) {
            $paramTypes[] = 'mixed';
        }

        if (\in_array('mixed', $paramTypes, true)) {
            return ['mixed'];
        }

        return array_unique($paramTypes);
    }

    private function getComment(BaseNode $node): string
    {
        $comment = '';
        if ('' !== $info = (string) $node->getInfo()) {
            $comment .= $info."\n";
        }

        if (!$node instanceof ArrayNode) {
            foreach ((array) ($node->getExample() ?? []) as $example) {
                $comment .= '@example '.$example."\n";
            }

            if ('' !== $default = $node->getDefaultValue()) {
                $comment .= '@default '.(null === $default ? 'null' : var_export($default, true))."\n";
            }

            if ($node instanceof EnumNode) {
                $comment .= \sprintf('@param ParamConfigurator|%s $value', implode('|', array_unique(array_map(fn ($a) => !$a instanceof \UnitEnum ? var_export($a, true) : '\\'.ltrim(var_export($a, true), '\\'), $node->getValues()))))."\n";
            } else {
                $parameterTypes = $this->getParameterTypes($node);
                $comment .= '@param ParamConfigurator|'.implode('|', $parameterTypes).' $value'."\n";
            }
        } else {
            foreach ((array) ($node->getExample() ?? []) as $example) {
                $comment .= '@example '.json_encode($example)."\n";
            }

            if ($node->hasDefaultValue() && [] != $default = $node->getDefaultValue()) {
                $comment .= '@default '.json_encode($default)."\n";
            }
        }

        if ($node->isDeprecated()) {
            $comment .= '@deprecated '.$node->getDeprecationMessage()."\n";
        }

        return $comment ? ' * '.str_replace("\n", "\n * ", rtrim($comment, "\n"))."\n" : '';
    }

    /**
     * Pick a good singular name.
     */
    private function getSingularName(PrototypedArrayNode $node): string
    {
        $name = $node->getName();
        if (!str_ends_with($name, 's')) {
            return $name;
        }

        $parent = $node->getParent();
        $mappings = $parent instanceof ArrayNode ? $parent->getXmlRemappings() : [];
        foreach ($mappings as $map) {
            if ($map[1] === $name) {
                $name = $map[0];
                break;
            }
        }

        return $name;
    }

    private function buildToArray(ClassBuilder $class, bool $rootClass = false): void
    {
        $body = '';
        if ($rootClass) {
            $body = 'if ($this->configOutput) {
        return $this->configOutput;
    }

    ';
        }

        $body .= '$output = [];';

        foreach ($class->getProperties() as $p) {
            if ('configOutput' === $p->getName()) {
                continue;
            }

            $code = '$this->PROPERTY';
            if (null !== $p->getType()) {
                if ($p->isArray()) {
                    $code = $p->areScalarsAllowed()
                        ? 'array_map(fn ($v) => $v instanceof CLASS ? $v->toArray() : $v, $this->PROPERTY)'
                        : 'array_map(fn ($v) => $v->toArray(), $this->PROPERTY)'
                    ;
                } else {
                    $code = $p->areScalarsAllowed()
                        ? '$this->PROPERTY instanceof CLASS ? $this->PROPERTY->toArray() : $this->PROPERTY'
                        : '$this->PROPERTY->toArray()'
                    ;
                }
            }

            $body .= strtr('
    if (isset($this->_usedProperties[\'PROPERTY\'])) {
        $output[\'ORG_NAME\'] = '.$code.';
    }', ['PROPERTY' => $p->getName(), 'ORG_NAME' => $p->getOriginalName(), 'CLASS' => $p->getType()]);
        }

        $extraKeys = $class->shouldAllowExtraKeys() ? ' + $this->_extraKeys' : '';

        $class->addMethod('toArray', '
public function NAME(): array
{
    '.$body.'

    return $output'.$extraKeys.';
}');
    }

    private function buildConstructor(ClassBuilder $class): void
    {
        $body = '';
        foreach ($class->getProperties() as $p) {
            if ('configOutput' === $p->getName()) {
                continue;
            }

            $code = '$value[\'ORG_NAME\']';
            if (null !== $p->getType()) {
                if ($p->isArray()) {
                    $code = $p->areScalarsAllowed()
                        ? 'array_map(fn ($v) => \is_array($v) ? new '.$p->getType().'($v) : $v, $value[\'ORG_NAME\'])'
                        : 'array_map(fn ($v) => new '.$p->getType().'($v), $value[\'ORG_NAME\'])'
                    ;
                } else {
                    $code = $p->areScalarsAllowed()
                        ? '\is_array($value[\'ORG_NAME\']) ? new '.$p->getType().'($value[\'ORG_NAME\']) : $value[\'ORG_NAME\']'
                        : 'new '.$p->getType().'($value[\'ORG_NAME\'])'
                    ;
                }
            }

            $body .= strtr('
    if (array_key_exists(\'ORG_NAME\', $value)) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY = '.$code.';
        unset($value[\'ORG_NAME\']);
    }
', ['PROPERTY' => $p->getName(), 'ORG_NAME' => $p->getOriginalName()]);
        }

        if ($class->shouldAllowExtraKeys()) {
            $body .= '
    $this->_extraKeys = $value;
';
        } else {
            $body .= '
    if ([] !== $value) {
        throw new InvalidConfigurationException(sprintf(\'The following keys are not supported by "%s": \', __CLASS__).implode(\', \', array_keys($value)));
    }';

            $class->addUse(InvalidConfigurationException::class);
        }

        $class->addMethod('__construct', '
public function __construct(array $value = [])
{'.$body.'
}');
    }

    private function buildSetExtraKey(ClassBuilder $class): void
    {
        if (!$class->shouldAllowExtraKeys()) {
            return;
        }

        $class->addUse(ParamConfigurator::class);

        $class->addProperty('_extraKeys');

        $class->addMethod('set', '
/**
 * @param ParamConfigurator|mixed $value
 *
 * @return $this
 */
public function NAME(string $key, mixed $value): static
{
    $this->_extraKeys[$key] = $value;

    return $this;
}');
    }

    private function getSubNamespace(ClassBuilder $rootClass): string
    {
        return \sprintf('%s\\%s', $rootClass->getNamespace(), substr($rootClass->getName(), 0, -6));
    }

    private function hasNormalizationClosures(NodeInterface $node): bool
    {
        try {
            $r = new \ReflectionProperty($node, 'normalizationClosures');
        } catch (\ReflectionException) {
            return false;
        }

        return [] !== $r->getValue($node);
    }

    private function getType(string $classType, bool $hasNormalizationClosures): string
    {
        return $classType.($hasNormalizationClosures ? '|scalar' : '');
    }

    private function getParamType(array $types, bool $withParamConfigurator = false): string
    {
        return \in_array('mixed', $types, true) ? 'mixed' : ($withParamConfigurator ? 'ParamConfigurator|' : '').implode('|', $types);
    }
}
