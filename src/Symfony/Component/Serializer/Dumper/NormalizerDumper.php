<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Dumper;

use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\NormalizerInterface;

/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 *
 * @experimental
 */
final class NormalizerDumper
{
    private $classMetadataFactory;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    public function dump(string $class, array $context = array())
    {
        $reflectionClass = new \ReflectionClass($class);
        if (!isset($context['class'])) {
            $context['class'] = $reflectionClass->getShortName().'Normalizer';
        }

        $namespaceLine = isset($context['namespace']) ? "\nnamespace {$context['namespace']};\n" : '';

        return <<<EOL
<?php
$namespaceLine
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\CircularReferenceTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * This class is generated.
 * Please do not update it manually.
 */
class {$context['class']} implements NormalizerInterface, NormalizerAwareInterface
{
    protected \$defaultContext = array(
        ObjectNormalizer::CIRCULAR_REFERENCE_LIMIT => 1,
    );

    use CircularReferenceTrait, NormalizerAwareTrait;

    public function __construct(array \$defaultContext = array())
    {
        \$this->defaultContext = array_merge(\$this->defaultContext, \$defaultContext);
    }

{$this->generateNormalizeMethod($reflectionClass)}

{$this->generateSupportsNormalizationMethod($reflectionClass)}
}
EOL;
    }

    /**
     * Generates the {@see NormalizerInterface::normalize} method.
     */
    private function generateNormalizeMethod(\ReflectionClass $reflectionClass): string
    {
        return <<<EOL
    public function normalize(\$object, \$format = null, array \$context = array())
    {
{$this->generateNormalizeMethodInner($reflectionClass)}
    }
EOL;
    }

    private function generateNormalizeMethodInner(\ReflectionClass $reflectionClass): string
    {
        $code = <<<EOL

        if (\$this->isCircularReference(\$object, \$context)) {
            return \$this->handleCircularReference(\$object, \$format, \$context);
        }

        \$groups = isset(\$context[ObjectNormalizer::GROUPS]) && is_array(\$context[ObjectNormalizer::GROUPS]) ? \$context[ObjectNormalizer::GROUPS] : null;

        \$output = array();
EOL;

        $attributesMetadata = $this->classMetadataFactory->getMetadataFor($reflectionClass->name)->getAttributesMetadata();
        $maxDepthCode = '';
        foreach ($attributesMetadata as $attributeMetadata) {
            if (null === $maxDepth = $attributeMetadata->getMaxDepth()) {
                continue;
            }

            $key = sprintf(ObjectNormalizer::DEPTH_KEY_PATTERN, $reflectionClass->name, $attributeMetadata->name);
            $maxDepthCode .= <<<EOL
            isset(\$context['{$key}']) ? ++\$context['{$key}'] : \$context['{$key}'] = 1;
EOL;
        }

        if ($maxDepthCode) {
            $code .= <<<EOL

        if (\$context[ObjectNormalizer::ENABLE_MAX_DEPTH] ?? \$this->defaultContext[ObjectNormalizer::ENABLE_MAX_DEPTH]) {{$maxDepthCode}
        }

EOL;
        }

        foreach ($attributesMetadata as $attributeMetadata) {
            $code .= <<<EOL

        \$attributes = \$context[ObjectNormalizer::ATTRIBUTES] ?? \$this->defaultContext[ObjectNormalizer::ATTRIBUTES] ?? null;
        if ((null === \$groups
EOL;

            if ($attributeMetadata->groups) {
                $code .= sprintf(" || array_intersect(\$groups, array('%s'))", implode("', '", $attributeMetadata->groups));
            }
            $code .= ')';

            $code .= " && (null === \$attributes || isset(\$attributes['{$attributeMetadata->name}']) || (is_array(\$attributes) && in_array('{$attributeMetadata->name}', \$attributes, true)))";

            if (null !== $maxDepth = $attributeMetadata->getMaxDepth()) {
                $key = sprintf(ObjectNormalizer::DEPTH_KEY_PATTERN, $reflectionClass->name, $attributeMetadata->name);
                $code .= " && (!isset(\$context['{$key}']) || {$maxDepth} >= \$context['{$key}'])";
            }

            $code .= ') {';

            $value = $this->generateGetAttributeValueExpression($attributeMetadata->name, $reflectionClass);
            $code .= <<<EOL

            \$value = {$value};
            if (is_scalar(\$value)) {
                \$output['{$attributeMetadata->name}'] = \$value;
            } else {
                \$subContext = \$context;
                if (isset(\$attributes['{$attributeMetadata->name}'])) {
                    \$subContext[ObjectNormalizer::ATTRIBUTES] = \$attributes['{$attributeMetadata->name}'];
                } else {
                    unset(\$subContext[ObjectNormalizer::ATTRIBUTES]);
                }

                \$output['{$attributeMetadata->name}'] = \$this->normalizer->normalize(\$value, \$format, \$subContext);
            }
        }
EOL;
        }

        $code .= <<<EOL


        return \$output;
EOL;

        return $code;
    }

    private function generateGetAttributeValueExpression(string $property, \ReflectionClass $reflectionClass): string
    {
        $camelProp = $this->camelize($property);

        foreach ($methods = array("get$camelProp", lcfirst($camelProp), "is$camelProp", "has$camelProp)") as $method) {
            if ($reflectionClass->hasMethod($method) && $reflectionClass->getMethod($method)) {
                return sprintf('$object->%s()', $method);
            }
        }

        if ($reflectionClass->hasProperty($property) && $reflectionClass->getProperty($property)->isPublic()) {
            return sprintf('$object->%s', $property);
        }

        if ($reflectionClass->hasMethod('__get') && $reflectionClass->getMethod('__get')) {
            return sprintf('$object->__get(\'%s\')', $property);
        }

        throw new \DomainException(sprintf('Neither the property "%s" nor one of the methods "%s()", "__get()" exist and have public access in class "%s".', $property, implode('()", "', $methods), $reflectionClass->name));
    }

    private function generateSupportsNormalizationMethod(\ReflectionClass $reflectionClass): string
    {
        $instanceof = '\\'.$reflectionClass->name;

        return <<<EOL
    public function supportsNormalization(\$data, \$format = null, array \$context = array())
    {
        return \$data instanceof {$instanceof};
    }
EOL;
    }

    private function camelize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}
