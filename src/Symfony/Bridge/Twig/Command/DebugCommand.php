<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

/**
 * Lists twig functions, filters, globals and tests present in the current project.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DebugCommand extends Command
{
    private $twig;

    /**
     * @param Environment $twig
     */
    public function __construct($twig = null)
    {
        parent::__construct();

        if (!$twig instanceof Environment) {
            @trigger_error(sprintf('Passing a command name as the first argument of "%s" is deprecated since version 3.4 and will be removed in 4.0. If the command was registered by convention, make it a service instead.', __METHOD__), E_USER_DEPRECATED);

            $this->setName(null === $twig ? 'debug:twig' : $twig);

            return;
        }

        $this->twig = $twig;
    }

    public function setTwigEnvironment(Environment $twig)
    {
        @trigger_error(sprintf('Method "%s" is deprecated since version 3.4 and will be removed in 4.0.', __METHOD__), E_USER_DEPRECATED);

        $this->twig = $twig;
    }

    /**
     * @return Environment $twig
     */
    protected function getTwigEnvironment()
    {
        @trigger_error(sprintf('Method "%s" is deprecated since version 3.4 and will be removed in 4.0.', __METHOD__), E_USER_DEPRECATED);

        return $this->twig;
    }

    protected function configure()
    {
        $this
            ->setName('debug:twig')
            ->setDefinition(array(
                new InputArgument('filter', InputArgument::OPTIONAL, 'Show details for all entries matching this filter'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (text or json)', 'text'),
            ))
            ->setDescription('Shows a list of twig functions, filters, globals and tests')
            ->setHelp(<<<'EOF'
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
        $io = new SymfonyStyle($input, $output);

        // BC to be removed in 4.0
        if (__CLASS__ !== get_class($this)) {
            $r = new \ReflectionMethod($this, 'getTwigEnvironment');
            if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                @trigger_error(sprintf('Usage of method "%s" is deprecated since version 3.4 and will no longer be supported in 4.0.', get_class($this).'::getTwigEnvironment'), E_USER_DEPRECATED);

                $this->twig = $this->getTwigEnvironment();
            }
        }
        if (null === $this->twig) {
            throw new \RuntimeException('The Twig environment needs to be set.');
        }

        $types = array('functions', 'filters', 'tests', 'globals');

        if ($input->getOption('format') === 'json') {
            $data = array();
            foreach ($types as $type) {
                foreach ($this->twig->{'get'.ucfirst($type)}() as $name => $entity) {
                    $data[$type][$name] = $this->getMetadata($type, $entity);
                }
            }
            $data['tests'] = array_keys($data['tests']);
            $io->writeln(json_encode($data));

            return 0;
        }

        $filter = $input->getArgument('filter');

        foreach ($types as $index => $type) {
            $items = array();
            foreach ($this->twig->{'get'.ucfirst($type)}() as $name => $entity) {
                if (!$filter || false !== strpos($name, $filter)) {
                    $items[$name] = $name.$this->getPrettyMetadata($type, $entity);
                }
            }

            if (!$items) {
                continue;
            }

            $io->section(ucfirst($type));

            ksort($items);
            $io->listing($items);
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
            $cb = $entity->getCallable();
            if (null === $cb) {
                return;
            }
            if (is_array($cb)) {
                if (!method_exists($cb[0], $cb[1])) {
                    return;
                }
                $refl = new \ReflectionMethod($cb[0], $cb[1]);
            } elseif (is_object($cb) && method_exists($cb, '__invoke')) {
                $refl = new \ReflectionMethod($cb, '__invoke');
            } elseif (function_exists($cb)) {
                $refl = new \ReflectionFunction($cb);
            } elseif (is_string($cb) && preg_match('{^(.+)::(.+)$}', $cb, $m) && method_exists($m[1], $m[2])) {
                $refl = new \ReflectionMethod($m[1], $m[2]);
            } else {
                throw new \UnexpectedValueException('Unsupported callback type');
            }

            $args = $refl->getParameters();

            // filter out context/environment args
            if ($entity->needsEnvironment()) {
                array_shift($args);
            }
            if ($entity->needsContext()) {
                array_shift($args);
            }

            if ($type === 'filters') {
                // remove the value the filter is applied on
                array_shift($args);
            }

            // format args
            $args = array_map(function ($param) {
                if ($param->isDefaultValueAvailable()) {
                    return $param->getName().' = '.json_encode($param->getDefaultValue());
                }

                return $param->getName();
            }, $args);

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
