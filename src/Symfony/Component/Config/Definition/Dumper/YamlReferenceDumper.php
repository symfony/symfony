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
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Yaml\Inline;

/**
 * Dumps a Yaml reference configuration for the given configuration/node instance.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class YamlReferenceDumper
{
    private $reference;

    public function dump(ConfigurationInterface $configuration)
    {
        return $this->dumpNode($configuration->getConfigTreeBuilder()->buildTree());
    }

    public function dumpNode(NodeInterface $node)
    {
        $this->reference = '';
        $this->writeNode($node);
        $ref = $this->reference;
        $this->reference = null;

        return $ref;
    }

    /**
     * @param NodeInterface $node
     * @param int           $depth
     */
    private function writeNode(NodeInterface $node, $depth = 0)
    {
        $comments = array();
        $default = '';
        $defaultArray = null;
        $children = null;
        $example = $node->getExample();

        // defaults
        if ($node instanceof ArrayNode) {
            $children = $node->getChildren();

            if ($node instanceof PrototypedArrayNode) {
                $prototype = $node->getPrototype();

                if ($prototype instanceof ArrayNode) {
                    $children = $prototype->getChildren();
                }

                // check for attribute as key
                if ($key = $node->getKeyAttribute()) {
                    $keyNodeClass = 'Symfony\Component\Config\Definition\\'.($prototype instanceof ArrayNode ? 'ArrayNode' : 'ScalarNode');
                    $keyNode = new $keyNodeClass($key, $node);

                    $info = 'Prototype';
                    if (null !== $prototype->getInfo()) {
                        $info .= ': '.$prototype->getInfo();
                    }
                    $keyNode->setInfo($info);

                    // add children
                    foreach ($children as $childNode) {
                        $keyNode->addChild($childNode);
                    }
                    $children = array($key => $keyNode);
                }
            }

            if (!$children) {
                if ($node->hasDefaultValue() && \count($defaultArray = $node->getDefaultValue())) {
                    $default = '';
                } elseif (!\is_array($example)) {
                    $default = '[]';
                }
            }
        } elseif ($node instanceof EnumNode) {
            $comments[] = 'One of '.implode('; ', array_map('json_encode', $node->getValues()));
            $default = $node->hasDefaultValue() ? Inline::dump($node->getDefaultValue()) : '~';
        } else {
            $default = '~';

            if ($node->hasDefaultValue()) {
                $default = $node->getDefaultValue();

                if (\is_array($default)) {
                    if (\count($defaultArray = $node->getDefaultValue())) {
                        $default = '';
                    } elseif (!\is_array($example)) {
                        $default = '[]';
                    }
                } else {
                    $default = Inline::dump($default);
                }
            }
        }

        // required?
        if ($node->isRequired()) {
            $comments[] = 'Required';
        }

        // example
        if ($example && !\is_array($example)) {
            $comments[] = 'Example: '.$example;
        }

        $default = '' != (string) $default ? ' '.$default : '';
        $comments = \count($comments) ? '# '.implode(', ', $comments) : '';

        $text = rtrim(sprintf('%-21s%s %s', $node->getName().':', $default, $comments), ' ');

        if ($info = $node->getInfo()) {
            $this->writeLine('');
            // indenting multi-line info
            $info = str_replace("\n", sprintf("\n%".($depth * 4).'s# ', ' '), $info);
            $this->writeLine('# '.$info, $depth * 4);
        }

        $this->writeLine($text, $depth * 4);

        // output defaults
        if ($defaultArray) {
            $this->writeLine('');

            $message = \count($defaultArray) > 1 ? 'Defaults' : 'Default';

            $this->writeLine('# '.$message.':', $depth * 4 + 4);

            $this->writeArray($defaultArray, $depth + 1);
        }

        if (\is_array($example)) {
            $this->writeLine('');

            $message = \count($example) > 1 ? 'Examples' : 'Example';

            $this->writeLine('# '.$message.':', $depth * 4 + 4);

            $this->writeArray($example, $depth + 1);
        }

        if ($children) {
            foreach ($children as $childNode) {
                $this->writeNode($childNode, $depth + 1);
            }
        }
    }

    /**
     * Outputs a single config reference line.
     *
     * @param string $text
     * @param int    $indent
     */
    private function writeLine($text, $indent = 0)
    {
        $indent = \strlen($text) + $indent;
        $format = '%'.$indent.'s';

        $this->reference .= sprintf($format, $text)."\n";
    }

    private function writeArray(array $array, $depth)
    {
        $isIndexed = array_values($array) === $array;

        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $val = '';
            } else {
                $val = $value;
            }

            if ($isIndexed) {
                $this->writeLine('- '.$val, $depth * 4);
            } else {
                $this->writeLine(sprintf('%-20s %s', $key.':', $val), $depth * 4);
            }

            if (\is_array($value)) {
                $this->writeArray($value, $depth + 1);
            }
        }
    }
}
