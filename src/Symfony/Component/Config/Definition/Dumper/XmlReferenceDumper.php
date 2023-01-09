<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Dumper;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;

/**
 * Dumps an XML reference configuration for the given configuration/node instance.
 *
 * @author Wouter J <waldio.webdesign@gmail.com>
 */
class XmlReferenceDumper
{
    private ?string $reference = null;

    public function dump(ConfigurationInterface $configuration, string $namespace = null)
    {
        return $this->dumpNode($configuration->getConfigTreeBuilder()->buildTree(), $namespace);
    }

    public function dumpNode(NodeInterface $node, string $namespace = null)
    {
        $this->reference = '';
        $this->writeNode($node, 0, true, $namespace);
        $ref = $this->reference;
        $this->reference = null;

        return $ref;
    }

    private function writeNode(NodeInterface $node, int $depth = 0, bool $root = false, string $namespace = null)
    {
        $rootName = ($root ? 'config' : $node->getName());
        $rootNamespace = ($namespace ?: ($root ? 'http://example.org/schema/dic/'.$node->getName() : null));

        // xml remapping
        if ($node->getParent()) {
            $remapping = array_filter($node->getParent()->getXmlRemappings(), function (array $mapping) use ($rootName) {
                return $rootName === $mapping[1];
            });

            if (\count($remapping)) {
                [$singular] = current($remapping);
                $rootName = $singular;
            }
        }
        $rootName = str_replace('_', '-', $rootName);

        $rootAttributes = [];
        $rootAttributeComments = [];
        $rootChildren = [];
        $rootComments = [];

        if ($node instanceof ArrayNode) {
            $children = $node->getChildren();

            // comments about the root node
            if ($rootInfo = $node->getInfo()) {
                $rootComments[] = $rootInfo;
            }

            if ($rootNamespace) {
                $rootComments[] = 'Namespace: '.$rootNamespace;
            }

            // render prototyped nodes
            if ($node instanceof PrototypedArrayNode) {
                $prototype = $node->getPrototype();

                $info = 'prototype';
                if (null !== $prototype->getInfo()) {
                    $info .= ': '.$prototype->getInfo();
                }
                array_unshift($rootComments, $info);

                if ($key = $node->getKeyAttribute()) {
                    $rootAttributes[$key] = str_replace('-', ' ', $rootName).' '.$key;
                }

                if ($prototype instanceof PrototypedArrayNode) {
                    $prototype->setName($key ?? '');
                    $children = [$key => $prototype];
                } elseif ($prototype instanceof ArrayNode) {
                    $children = $prototype->getChildren();
                } else {
                    if ($prototype->hasDefaultValue()) {
                        $prototypeValue = $prototype->getDefaultValue();
                    } else {
                        $prototypeValue = match (\get_class($prototype)) {
                            ScalarNode::class => 'scalar value',
                            FloatNode::class,
                            IntegerNode::class => 'numeric value',
                            BooleanNode::class => 'true|false',
                            EnumNode::class => implode('|', array_map('json_encode', $prototype->getValues())),
                            default => 'value',
                        };
                    }
                }
            }

            // get attributes and elements
            foreach ($children as $child) {
                if ($child instanceof ArrayNode) {
                    // get elements
                    $rootChildren[] = $child;

                    continue;
                }

                // get attributes

                // metadata
                $name = str_replace('_', '-', $child->getName());
                $value = '%%%%not_defined%%%%'; // use a string which isn't used in the normal world

                // comments
                $comments = [];
                if ($child instanceof BaseNode && $info = $child->getInfo()) {
                    $comments[] = $info;
                }

                if ($child instanceof BaseNode && $example = $child->getExample()) {
                    $comments[] = 'Example: '.(\is_array($example) ? implode(', ', $example) : $example);
                }

                if ($child->isRequired()) {
                    $comments[] = 'Required';
                }

                if ($child instanceof BaseNode && $child->isDeprecated()) {
                    $deprecation = $child->getDeprecation($child->getName(), $node->getPath());
                    $comments[] = sprintf('Deprecated (%s)', ($deprecation['package'] || $deprecation['version'] ? "Since {$deprecation['package']} {$deprecation['version']}: " : '').$deprecation['message']);
                }

                if ($child instanceof EnumNode) {
                    $comments[] = 'One of '.implode('; ', array_map('json_encode', $child->getValues()));
                }

                if (\count($comments)) {
                    $rootAttributeComments[$name] = implode(";\n", $comments);
                }

                // default values
                if ($child->hasDefaultValue()) {
                    $value = $child->getDefaultValue();
                }

                // append attribute
                $rootAttributes[$name] = $value;
            }
        }

        // render comments

        // root node comment
        if (\count($rootComments)) {
            foreach ($rootComments as $comment) {
                $this->writeLine('<!-- '.$comment.' -->', $depth);
            }
        }

        // attribute comments
        if (\count($rootAttributeComments)) {
            foreach ($rootAttributeComments as $attrName => $comment) {
                $commentDepth = $depth + 4 + \strlen($attrName) + 2;
                $commentLines = explode("\n", $comment);
                $multiline = (\count($commentLines) > 1);
                $comment = implode(\PHP_EOL.str_repeat(' ', $commentDepth), $commentLines);

                if ($multiline) {
                    $this->writeLine('<!--', $depth);
                    $this->writeLine($attrName.': '.$comment, $depth + 4);
                    $this->writeLine('-->', $depth);
                } else {
                    $this->writeLine('<!-- '.$attrName.': '.$comment.' -->', $depth);
                }
            }
        }

        // render start tag + attributes
        $rootIsVariablePrototype = isset($prototypeValue);
        $rootIsEmptyTag = (0 === \count($rootChildren) && !$rootIsVariablePrototype);
        $rootOpenTag = '<'.$rootName;
        if (1 >= ($attributesCount = \count($rootAttributes))) {
            if (1 === $attributesCount) {
                $rootOpenTag .= sprintf(' %s="%s"', current(array_keys($rootAttributes)), $this->writeValue(current($rootAttributes)));
            }

            $rootOpenTag .= $rootIsEmptyTag ? ' />' : '>';

            if ($rootIsVariablePrototype) {
                $rootOpenTag .= $prototypeValue.'</'.$rootName.'>';
            }

            $this->writeLine($rootOpenTag, $depth);
        } else {
            $this->writeLine($rootOpenTag, $depth);

            $i = 1;

            foreach ($rootAttributes as $attrName => $attrValue) {
                $attr = sprintf('%s="%s"', $attrName, $this->writeValue($attrValue));

                $this->writeLine($attr, $depth + 4);

                if ($attributesCount === $i++) {
                    $this->writeLine($rootIsEmptyTag ? '/>' : '>', $depth);

                    if ($rootIsVariablePrototype) {
                        $rootOpenTag .= $prototypeValue.'</'.$rootName.'>';
                    }
                }
            }
        }

        // render children tags
        foreach ($rootChildren as $child) {
            $this->writeLine('');
            $this->writeNode($child, $depth + 4);
        }

        // render end tag
        if (!$rootIsEmptyTag && !$rootIsVariablePrototype) {
            $this->writeLine('');

            $rootEndTag = '</'.$rootName.'>';
            $this->writeLine($rootEndTag, $depth);
        }
    }

    /**
     * Outputs a single config reference line.
     */
    private function writeLine(string $text, int $indent = 0)
    {
        $indent = \strlen($text) + $indent;
        $format = '%'.$indent.'s';

        $this->reference .= sprintf($format, $text).\PHP_EOL;
    }

    /**
     * Renders the string conversion of the value.
     */
    private function writeValue(mixed $value): string
    {
        if ('%%%%not_defined%%%%' === $value) {
            return '';
        }

        if (\is_string($value) || is_numeric($value)) {
            return $value;
        }

        if (false === $value) {
            return 'false';
        }

        if (true === $value) {
            return 'true';
        }

        if (null === $value) {
            return 'null';
        }

        if (empty($value)) {
            return '';
        }

        if (\is_array($value)) {
            return implode(',', $value);
        }

        return '';
    }
}
