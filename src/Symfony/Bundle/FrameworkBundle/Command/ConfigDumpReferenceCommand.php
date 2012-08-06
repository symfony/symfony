<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * A console command for dumping available configuration reference
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ConfigDumpReferenceCommand extends ContainerDebugCommand
{
    protected $output;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('config:dump-reference')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::REQUIRED, 'The Bundle or extension alias')
            ))
            ->setDescription('Dumps default configuration for an extension')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command dumps the default configuration for an extension/bundle.

The extension alias or bundle name can be used:

Example:

  <info>php %command.full_name% framework</info>

or

  <info>php %command.full_name% FrameworkBundle</info>
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $bundles = $this->getContainer()->get('kernel')->getBundles();
        $containerBuilder = $this->getContainerBuilder();

        $name = $input->getArgument('name');

        $extension = null;

        if (preg_match('/Bundle$/', $name)) {
            // input is bundle name

            if (isset($bundles[$name])) {
                $extension = $bundles[$name]->getContainerExtension();
            }

            if (!$extension) {
                throw new \LogicException('No extensions with configuration available for "'.$name.'"');
            }

            $message = 'Default configuration for "'.$name.'"';
        } else {
            foreach ($bundles as $bundle) {
                $extension = $bundle->getContainerExtension();

                if ($extension && $extension->getAlias() === $name) {
                    break;
                }

                $extension = null;
            }

            if (!$extension) {
                throw new \LogicException('No extension with alias "'.$name.'" is enabled');
            }

            $message = 'Default configuration for extension with alias: "'.$name.'"';
        }

        $configuration = $extension->getConfiguration(array(), $containerBuilder);

        if (!$configuration) {
            throw new \LogicException('The extension with alias "'.$extension->getAlias().
                    '" does not have it\'s getConfiguration() method setup');
        }

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException(
                'Configuration class "'.get_class($configuration).
                '" should implement ConfigurationInterface in order to be dumpable');
        }

        $rootNode = $configuration->getConfigTreeBuilder()->buildTree();

        $output->writeln($message);

        // root node
        $this->outputNode($rootNode);
    }

    /**
     * Outputs a single config reference line
     *
     * @param string $text
     * @param int    $indent
     */
    private function outputLine($text, $indent = 0)
    {
        $indent = strlen($text) + $indent;

        $format = '%'.$indent.'s';

        $this->output->writeln(sprintf($format, $text));
    }

    private function outputArray(array $array, $depth)
    {
        $isIndexed = array_values($array) === $array;

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $val = '';
            } else {
                $val = $value;
            }

            if ($isIndexed) {
                $this->outputLine('- '.$val, $depth * 4);
            } else {
                $this->outputLine(sprintf('%-20s %s', $key.':', $val), $depth * 4);
            }

            if (is_array($value)) {
                $this->outputArray($value, $depth + 1);
            }
        }
    }

    /**
     * @param NodeInterface $node
     * @param int           $depth
     */
    private function outputNode(NodeInterface $node, $depth = 0)
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
                    $keyNode = new ArrayNode($key, $node);
                    $keyNode->setInfo('Prototype');

                    // add children
                    foreach ($children as $childNode) {
                        $keyNode->addChild($childNode);
                    }
                    $children = array($key => $keyNode);
                }
            }

            if (!$children) {
                if ($node->hasDefaultValue() && count($defaultArray = $node->getDefaultValue())) {
                    $default = '';
                } elseif (!is_array($example)) {
                    $default = '[]';
                }
            }
        } else {
            $default = '~';

            if ($node->hasDefaultValue()) {
                $default = $node->getDefaultValue();

                if (true === $default) {
                    $default = 'true';
                } elseif (false === $default) {
                    $default = 'false';
                } elseif (null === $default) {
                    $default = '~';
                }
            }
        }

        // required?
        if ($node->isRequired()) {
            $comments[] = 'Required';
        }

        // example
        if ($example && !is_array($example)) {
            $comments[] = 'Example: '.$example;
        }

        $default = (string) $default != '' ? ' '.$default : '';
        $comments = count($comments) ? '# '.implode(', ', $comments) : '';

        $text = sprintf('%-20s %s %s', $node->getName().':', $default, $comments);

        if ($info = $node->getInfo()) {
            $this->outputLine('');
            $this->outputLine('# '.$info, $depth * 4);
        }

        $this->outputLine($text, $depth * 4);

        // output defaults
        if ($defaultArray) {
            $this->outputLine('');

            $message = count($defaultArray) > 1 ? 'Defaults' : 'Default';

            $this->outputLine('# '.$message.':', $depth * 4 + 4);

            $this->outputArray($defaultArray, $depth + 1);
        }

        if (is_array($example)) {
            $this->outputLine('');

            $message = count($example) > 1 ? 'Examples' : 'Example';

            $this->outputLine('# '.$message.':', $depth * 4 + 4);

            $this->outputArray($example, $depth + 1);
        }

        if ($children) {
            foreach ($children as $childNode) {
                $this->outputNode($childNode, $depth + 1);
            }
        }
    }
}
