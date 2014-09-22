<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists twig functions, filters, globals and tests present in the current project
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DebugCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('twig:debug')
            ->setDefinition(array(
                new InputArgument('filter', InputArgument::OPTIONAL, 'Show details for all entries matching this filter'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text'),
            ))
            ->setDescription('Shows a list of twig functions, filters, globals and tests')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command outputs a list of twig functions,
filters, globals and tests. Output can be filtered with an optional argument.

<info>php %command.full_name%</info>

The command lists all functions, filters, etc.

<info>php %command.full_name% date</info>

The command lists everything that contains the word date.

<info>php %command.full_name% --format=json</info>

The command lists everything in a machine readable json format.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $twig = $this->getContainer()->get('twig');
        $types = array('functions', 'filters', 'tests', 'globals');

        if ($input->getOption('format') === 'json') {
            $data = array();
            foreach ($types as $type) {
                foreach ($twig->{'get'.ucfirst($type)}() as $name => $entity) {
                    $data[$type][$name] = $this->getMetadata($type, $entity);
                }
            }
            $data['tests'] = array_keys($data['tests']);
            $output->writeln(json_encode($data));

            return 0;
        }

        $filter = $input->getArgument('filter');

        foreach ($types as $index => $type) {
            $items = array();
            foreach ($twig->{'get'.ucfirst($type)}() as $name => $entity) {
                if (!$filter || false !== strpos($name, $filter)) {
                    $items[$name] = $name.$this->getPrettyMetadata($type, $entity);
                }
            }

            if (!$items) {
                continue;
            }
            if ($index > 0) {
                $output->writeln('');
            }
            $output->writeln('<info>'.ucfirst($type).'</info>');
            ksort($items);
            foreach ($items as $item) {
                $output->writeln('  '.$item);
            }
        }

        return 0;
    }

    private function getMetadata($type, $entity)
    {
        if ($type === 'globals') {
            return $entity;
        }
        if ($type === 'tests') {
            return;
        }
        if ($type === 'functions' || $type === 'filters') {
            $args = array();
            $cb = $entity->getCallable();
            if (is_null($cb)) {
                return;
            }
            if (is_array($cb)) {
                if (!method_exists($cb[0], $cb[1])) {
                    return;
                }
                $refl = new \ReflectionMethod($cb[0], $cb[1]);
            } elseif (is_object($cb) && is_callable($cb)) {
                $refl = new \ReflectionMethod($cb, '__invoke');
            } elseif (function_exists($cb)) {
                $refl = new \ReflectionFunction($cb);
            } elseif (is_string($cb) && preg_match('{^(.+)::(.+)$}', $cb, $m) && method_exists($m[1], $m[2])) {
                $refl = new \ReflectionMethod($m[1], $m[2]);
            } else {
                throw new \UnexpectedValueException('Unsupported callback type');
            }

            // filter out context/environment args
            $args = array_filter($refl->getParameters(), function ($param) use ($entity) {
                if ($entity->needsContext() && $param->getName() === 'context') {
                    return false;
                }

                return !$param->getClass() || $param->getClass()->getName() !== 'Twig_Environment';
            });

            // format args
            $args = array_map(function ($param) {
                if ($param->isDefaultValueAvailable()) {
                    return $param->getName().' = '.json_encode($param->getDefaultValue());
                }

                return $param->getName();
            }, $args);

            if ($type === 'filters') {
                // remove the value the filter is applied on
                array_shift($args);
            }

            return $args;
        }
    }

    private function getPrettyMetadata($type, $entity)
    {
        if ($type === 'tests') {
            return '';
        }

        try {
            $meta = $this->getMetadata($type, $entity);
            if ($meta === null) {
                return '(unknown?)';
            }
        } catch (\UnexpectedValueException $e) {
            return ' <error>'.$e->getMessage().'</error>';
        }

        if ($type === 'globals') {
            if (is_object($meta)) {
                return ' = object('.get_class($meta).')';
            }

            return ' = '.substr(@json_encode($meta), 0, 50);
        }

        if ($type === 'functions') {
            return '('.implode(', ', $meta).')';
        }

        if ($type === 'filters') {
            return $meta ? '('.implode(', ', $meta).')' : '';
        }
    }
}
