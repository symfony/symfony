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

/**
 * Generate ConfigBuilders to help create valid config.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @deprecated since Symfony 7.4
 */
class ConfigBuilderGenerator implements ConfigBuilderGeneratorInterface
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
        $rootClass = new ClassBuilder('Symfony\\Config', $rootNode->getName(), $rootNode, true);

        $path = $this->getFullPath($rootClass);
        if (!is_file($path)) {
            // Generate the class if the file not exists
            $this->classes[] = $rootClass;
            $this->buildNode($rootNode, $rootClass, $this->getSubNamespace($rootClass));
            $rootClass->addImplements(ConfigBuilderInterface::class);
            $rootClass->addMethod('getExtensionAlias', '
public function NAME(): string
{
    return \'ALIAS\';
}', ['ALIAS' => $rootNode->getPath()]);

            $this->writeClasses();
        }

        return static function () use ($path, $rootClass) {
            require_once $path;
            $className = $rootClass->getFqcn();

            return new $className();
        };
    }

    private function getFullPath(ClassBuilder $class): string
    {
        $directory = $this->outputDir.\DIRECTORY_SEPARATOR.$class->getDirectory();
        if (!is_dir($directory)) {
            @mkdir($directory, 0o777, true);
        }

        return $directory.\DIRECTORY_SEPARATOR.$class->getFilename();
    }

    private function writeClasses(): void
    {
        foreach ($this->classes as $class) {
            $this->buildConstructor($class, $class->getNode());
            $this->buildToArray($class);
            if ($class->getProperties()) {
                $class->addProperty('_usedProperties', null, '[]');
            }
            if ($class->isRoot) {
                $class->addProperty('_hasDeprecatedCalls', null, 'false');
            }
            $this->buildSetExtraKey($class);

            file_put_contents($this->getFullPath($class), $class->build());
        }

        $this->classes = [];
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
                default => throw new \RuntimeException(\sprintf('Unknown node "%s".', get_debug_type($child))),
            };
        }
    }

    private function handleArrayNode(ArrayNode $node, ClassBuilder $class, string $namespace): void
    {
        $childClass = new ClassBuilder($namespace, $node->getName(), $node);
        $childClass->setAllowExtraKeys($node->shouldIgnoreExtraKeys());
        $class->addRequire($childClass);
        $this->classes[] = $childClass;

        $nodeTypes = $this->getParameterTypes($node);
        $paramType = implode('|', $nodeTypes);
        $acceptScalar = 'array' !== $paramType;

        $comment = $this->getComment($node);
        if ($acceptScalar) {
            $comment = \sprintf(" * @template TValue of %s\n * @param TValue \$value\n%s", $paramType, $comment);
            $comment .= \sprintf(' * @return %s|$this'."\n", $childClass->getFqcn());
            $comment .= \sprintf(' * @psalm-return (TValue is array ? %s : static)'."\n", $childClass->getFqcn());
        }
        if ($class->isRoot) {
            $comment .= " * @deprecated since Symfony 7.4\n";
        }
        if ('' !== $comment) {
            $comment = "/**\n$comment */\n";
        }

        $property = $class->addProperty(
            $node->getName(),
            $childClass->getFqcn().($acceptScalar ? '|scalar' : '')
        );
        $body = $acceptScalar ? '
COMMENTpublic function NAME(PARAM_TYPE $value = []): CLASS|static
{DEPRECATED_BODY
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
{DEPRECATED_BODY
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
            'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
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
 *DEPRECATED_ANNOTATION/
public function NAME(mixed $valueDEFAULT): static
{DEPRECATED_BODY
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY = $value;

    return $this;
}';
        $class->addMethod($node->getName(), $body, [
            'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
            'DEPRECATED_ANNOTATION' => $class->isRoot ? " @deprecated since Symfony 7.4\n *" : '',
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

        $nodeParameterTypes = $this->getParameterTypes($node);
        $prototypeParameterTypes = $this->getParameterTypes($prototype);
        $noKey = null === $key = $node->getKeyAttribute();
        $acceptScalar = ['array'] !== $nodeParameterTypes || ['array'] !== $prototypeParameterTypes;

        if (!$prototype instanceof ArrayNode || ($prototype instanceof PrototypedArrayNode && $prototype->getPrototype() instanceof ScalarNode)) {
            $class->addUse(ParamConfigurator::class);
            $property = $class->addProperty($node->getName());
            if ($noKey) {
                // This is an array of values; don't use singular name
                $nodeTypesWithoutArray = array_diff($nodeParameterTypes, ['array']);
                $body = '
/**
 * @param ParamConfigurator|list<ParamConfigurator|PROTOTYPE_TYPE>EXTRA_TYPE $value
 *
 * @return $this
 *DEPRECATED_ANNOTATION/
public function NAME(PARAM_TYPE $value): static
{DEPRECATED_BODY
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY = $value;

    return $this;
}';

                $class->addMethod($node->getName(), $body, [
                    'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
                    'DEPRECATED_ANNOTATION' => $class->isRoot ? " @deprecated since Symfony 7.4\n *" : '',
                    'PROPERTY' => $property->getName(),
                    'PROTOTYPE_TYPE' => implode('|', $prototypeParameterTypes),
                    'EXTRA_TYPE' => $nodeTypesWithoutArray ? '|'.implode('|', $nodeTypesWithoutArray) : '',
                    'PARAM_TYPE' => ['mixed'] !== $nodeParameterTypes ? 'ParamConfigurator|'.implode('|', $nodeParameterTypes) : 'mixed',
                ]);
            } else {
                $body = '
/**
 * @return $this
 *DEPRECATED_ANNOTATION/
public function NAME(string $VAR, TYPE $VALUE): static
{DEPRECATED_BODY
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY[$VAR] = $VALUE;

    return $this;
}';

                $class->addMethod($methodName, $body, [
                    'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
                    'DEPRECATED_ANNOTATION' => $class->isRoot ? " @deprecated since Symfony 7.4\n *" : '',
                    'PROPERTY' => $property->getName(),
                    'TYPE' => ['mixed'] !== $prototypeParameterTypes ? 'ParamConfigurator|'.implode('|', $prototypeParameterTypes) : 'mixed',
                    'VAR' => '' === $key ? 'key' : $key,
                    'VALUE' => 'value' === $key ? 'data' : 'value',
                ]);
            }

            return;
        }

        $childClass = new ClassBuilder($namespace, $name, $prototype);
        if ($prototype instanceof ArrayNode) {
            $childClass->setAllowExtraKeys($prototype->shouldIgnoreExtraKeys());
        }
        $class->addRequire($childClass);
        $this->classes[] = $childClass;

        $property = $class->addProperty(
            $node->getName(),
            $childClass->getFqcn().'[]'.($acceptScalar ? '|scalar' : '')
        );

        $paramType = implode('|', $noKey ? $nodeParameterTypes : $prototypeParameterTypes);
        $acceptScalar = 'array' !== $paramType;

        $comment = $this->getComment($node);
        if ($acceptScalar) {
            $comment = \sprintf(" * @template TValue of %s\n * @param TValue \$value\n%s", $paramType, $comment);
            $comment .= \sprintf(' * @return %s|$this'."\n", $childClass->getFqcn());
            $comment .= \sprintf(' * @psalm-return (TValue is array ? %s : static)'."\n", $childClass->getFqcn());
        }
        if ($class->isRoot) {
            $comment .= " * @deprecated since Symfony 7.4\n";
        }
        if ('' !== $comment) {
            $comment = "/**\n$comment */\n";
        }

        if ($noKey) {
            $body = $acceptScalar ? '
COMMENTpublic function NAME(PARAM_TYPE $value = []): CLASS|static
{DEPRECATED_BODY
    $this->_usedProperties[\'PROPERTY\'] = true;
    if (!\is_array($value)) {
        $this->PROPERTY[] = $value;

        return $this;
    }

    return $this->PROPERTY[] = new CLASS($value);
}' : '
COMMENTpublic function NAME(array $value = []): CLASS
{DEPRECATED_BODY
    $this->_usedProperties[\'PROPERTY\'] = true;

    return $this->PROPERTY[] = new CLASS($value);
}';
            $class->addMethod($methodName, $body, [
                'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
                'COMMENT' => $comment,
                'PROPERTY' => $property->getName(),
                'CLASS' => $childClass->getFqcn(),
                'PARAM_TYPE' => $paramType,
            ]);
        } else {
            $body = $acceptScalar ? '
COMMENTpublic function NAME(string $VAR, PARAM_TYPE $VALUE = []): CLASS|static
{DEPRECATED_BODY
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
{DEPRECATED_BODY
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
                'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
                'COMMENT' => $comment,
                'PROPERTY' => $property->getName(),
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
 *DEPRECATED_ANNOTATION/
public function NAME($value): static
{DEPRECATED_BODY
    $this->_usedProperties[\'PROPERTY\'] = true;
    $this->PROPERTY = $value;

    return $this;
}';

        $class->addMethod($node->getName(), $body, [
            'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
            'DEPRECATED_ANNOTATION' => $class->isRoot ? " @deprecated since Symfony 7.4\n *" : '',
            'PROPERTY' => $property->getName(),
            'COMMENT' => $comment,
        ]);
    }

    private function getParameterTypes(NodeInterface $node): array
    {
        $paramTypes = [];
        if ($node instanceof BaseNode) {
            foreach ($node->getNormalizedTypes() as $type) {
                if (ExprBuilder::TYPE_ANY === $type) {
                    return ['mixed'];
                }

                $paramTypes[] = match ($type) {
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
        } elseif ($node instanceof ArrayNode) {
            $paramTypes[] = 'array';
        } else {
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

    private function buildToArray(ClassBuilder $class): void
    {
        $body = '$output = [];';
        foreach ($class->getProperties() as $p) {
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
        $output[\'ORIG_NAME\'] = '.$code.';
    }', ['PROPERTY' => $p->getName(), 'ORIG_NAME' => $p->getOriginalName(), 'CLASS' => $p->getType()]);
        }

        $extraKeys = $class->shouldAllowExtraKeys() ? ' + $this->_extraKeys' : '';

        if ($class->isRoot) {
            $body .= "
    if (\$this->_hasDeprecatedCalls) {
        trigger_deprecation('symfony/config', '7.4', 'Calling any fluent method on \"%s\" is deprecated; pass the configuration to the constructor instead.', \$this::class);
    }";
        }

        $class->addMethod('toArray', '
public function NAME(): array
{
    '.$body.'

    return $output'.$extraKeys.';
}');
    }

    private function buildConstructor(ClassBuilder $class, NodeInterface $node): void
    {
        $body = '';
        foreach ($class->getProperties() as $p) {
            $code = '$config[\'ORIG_NAME\']';
            if (null !== $p->getType()) {
                if ($p->isArray()) {
                    $code = $p->areScalarsAllowed()
                        ? 'array_map(fn ($v) => \is_array($v) ? new '.$p->getType().'($v) : $v, $config[\'ORIG_NAME\'])'
                        : 'array_map(fn ($v) => new '.$p->getType().'($v), $config[\'ORIG_NAME\'])'
                    ;
                } else {
                    $code = $p->areScalarsAllowed()
                        ? '\is_array($config[\'ORIG_NAME\']) ? new '.$p->getType().'($config[\'ORIG_NAME\']) : $config[\'ORIG_NAME\']'
                        : 'new '.$p->getType().'($config[\'ORIG_NAME\'])'
                    ;
                }
            }

            $body .= strtr('
    if (array_key_exists(\'ORIG_NAME\', $config)) {
        $this->_usedProperties[\'PROPERTY\'] = true;
        $this->PROPERTY = '.$code.';
        unset($config[\'ORIG_NAME\']);
    }
', ['PROPERTY' => $p->getName(), 'ORIG_NAME' => $p->getOriginalName()]);
        }

        if ($class->shouldAllowExtraKeys()) {
            $body .= '
    $this->_extraKeys = $config;
';
        } else {
            $body .= '
    if ($config) {
        throw new InvalidConfigurationException(sprintf(\'The following keys are not supported by "%s": \', __CLASS__).implode(\', \', array_keys($config)));
    }';

            $class->addUse(InvalidConfigurationException::class);
        }

        $class->addMethod('__construct', '
public function __construct(array $config = [])
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
 *DEPRECATED_ANNOTATION/
public function NAME(string $key, mixed $value): static
{DEPRECATED_BODY
    $this->_extraKeys[$key] = $value;

    return $this;
}', [
            'DEPRECATED_BODY' => $class->isRoot ? "\n    \$this->_hasDeprecatedCalls = true;" : '',
            'DEPRECATED_ANNOTATION' => $class->isRoot ? " @deprecated since Symfony 7.4\n *" : '',
        ]);
    }

    private function getSubNamespace(ClassBuilder $rootClass): string
    {
        return \sprintf('%s\\%s', $rootClass->getNamespace(), substr($rootClass->getName(), 0, -6));
    }
}
