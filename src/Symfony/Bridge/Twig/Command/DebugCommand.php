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
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Lists twig functions, filters, globals and tests present in the current project.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DebugCommand extends Command
{
    protected static $defaultName = 'debug:twig';

    private $twig;
    private $projectDir;

    /**
     * @param Environment $twig
     * @param string|null $projectDir
     */
    public function __construct($twig = null, $projectDir = null)
    {
        if (!$twig instanceof Environment) {
            @trigger_error(sprintf('Passing a command name as the first argument of "%s()" is deprecated since Symfony 3.4 and support for it will be removed in 4.0. If the command was registered by convention, make it a service instead.', __METHOD__), \E_USER_DEPRECATED);

            parent::__construct($twig);

            return;
        }

        parent::__construct();

        $this->twig = $twig;
        $this->projectDir = $projectDir;
    }

    public function setTwigEnvironment(Environment $twig)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0.', __METHOD__), \E_USER_DEPRECATED);

        $this->twig = $twig;
    }

    /**
     * @return Environment $twig
     */
    protected function getTwigEnvironment()
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0.', __METHOD__), \E_USER_DEPRECATED);

        return $this->twig;
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('filter', InputArgument::OPTIONAL, 'Show details for all entries matching this filter'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (text or json)', 'text'),
            ])
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
        $decorated = $io->isDecorated();

        // BC to be removed in 4.0
        if (__CLASS__ !== static::class) {
            $r = new \ReflectionMethod($this, 'getTwigEnvironment');
            if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                @trigger_error(sprintf('Usage of method "%s" is deprecated since Symfony 3.4 and will no longer be supported in 4.0. Construct the command with its required arguments instead.', static::class.'::getTwigEnvironment'), \E_USER_DEPRECATED);

                $this->twig = $this->getTwigEnvironment();
            }
        }
        if (null === $this->twig) {
            throw new \RuntimeException('The Twig environment needs to be set.');
        }

        $filter = $input->getArgument('filter');
        $types = ['functions', 'filters', 'tests', 'globals'];

        if ('json' === $input->getOption('format')) {
            $data = [];
            foreach ($types as $type) {
                foreach ($this->twig->{'get'.ucfirst($type)}() as $name => $entity) {
                    if (!$filter || false !== strpos($name, $filter)) {
                        $data[$type][$name] = $this->getMetadata($type, $entity);
                    }
                }
            }

            if (isset($data['tests'])) {
                $data['tests'] = array_keys($data['tests']);
            }

            $data['loader_paths'] = $this->getLoaderPaths();
            $data = json_encode($data, \JSON_PRETTY_PRINT);
            $io->writeln($decorated ? OutputFormatter::escape($data) : $data);

            return 0;
        }

        foreach ($types as $index => $type) {
            $items = [];
            foreach ($this->twig->{'get'.ucfirst($type)}() as $name => $entity) {
                if (!$filter || false !== strpos($name, $filter)) {
                    $items[$name] = $name.$this->getPrettyMetadata($type, $entity, $decorated);
                }
            }

            if (!$items) {
                continue;
            }

            $io->section(ucfirst($type));

            ksort($items);
            $io->listing($items);
        }

        $rows = [];
        $firstNamespace = true;
        $prevHasSeparator = false;
        foreach ($this->getLoaderPaths() as $namespace => $paths) {
            if (!$firstNamespace && !$prevHasSeparator && \count($paths) > 1) {
                $rows[] = ['', ''];
            }
            $firstNamespace = false;
            foreach ($paths as $path) {
                $rows[] = [$namespace, $path.\DIRECTORY_SEPARATOR];
                $namespace = '';
            }
            if (\count($paths) > 1) {
                $rows[] = ['', ''];
                $prevHasSeparator = true;
            } else {
                $prevHasSeparator = false;
            }
        }
        if ($prevHasSeparator) {
            array_pop($rows);
        }
        $io->section('Loader Paths');
        $io->table(['Namespace', 'Paths'], $rows);

        return 0;
    }

    private function getLoaderPaths()
    {
        if (!($loader = $this->twig->getLoader()) instanceof FilesystemLoader) {
            return [];
        }

        $loaderPaths = [];
        foreach ($loader->getNamespaces() as $namespace) {
            $paths = array_map(function ($path) {
                if (null !== $this->projectDir && 0 === strpos($path, $this->projectDir)) {
                    $path = ltrim(substr($path, \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
                }

                return $path;
            }, $loader->getPaths($namespace));

            if (FilesystemLoader::MAIN_NAMESPACE === $namespace) {
                $namespace = '(None)';
            } else {
                $namespace = '@'.$namespace;
            }

            $loaderPaths[$namespace] = $paths;
        }

        return $loaderPaths;
    }

    private function getMetadata($type, $entity)
    {
        if ('globals' === $type) {
            return $entity;
        }
        if ('tests' === $type) {
            return null;
        }
        if ('functions' === $type || 'filters' === $type) {
            $cb = $entity->getCallable();
            if (null === $cb) {
                return null;
            }
            if (\is_array($cb)) {
                if (!method_exists($cb[0], $cb[1])) {
                    return null;
                }
                $refl = new \ReflectionMethod($cb[0], $cb[1]);
            } elseif (\is_object($cb) && method_exists($cb, '__invoke')) {
                $refl = new \ReflectionMethod($cb, '__invoke');
            } elseif (\function_exists($cb)) {
                $refl = new \ReflectionFunction($cb);
            } elseif (\is_string($cb) && preg_match('{^(.+)::(.+)$}', $cb, $m) && method_exists($m[1], $m[2])) {
                $refl = new \ReflectionMethod($m[1], $m[2]);
            } else {
                throw new \UnexpectedValueException('Unsupported callback type.');
            }

            $args = $refl->getParameters();

            // filter out context/environment args
            if ($entity->needsEnvironment()) {
                array_shift($args);
            }
            if ($entity->needsContext()) {
                array_shift($args);
            }

            if ('filters' === $type) {
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

        return null;
    }

    private function getPrettyMetadata($type, $entity, $decorated)
    {
        if ('tests' === $type) {
            return '';
        }

        try {
            $meta = $this->getMetadata($type, $entity);
            if (null === $meta) {
                return '(unknown?)';
            }
        } catch (\UnexpectedValueException $e) {
            return sprintf(' <error>%s</error>', $decorated ? OutputFormatter::escape($e->getMessage()) : $e->getMessage());
        }

        if ('globals' === $type) {
            if (\is_object($meta)) {
                return ' = object('.\get_class($meta).')';
            }

            $description = substr(@json_encode($meta), 0, 50);

            return sprintf(' = %s', $decorated ? OutputFormatter::escape($description) : $description);
        }

        if ('functions' === $type) {
            return '('.implode(', ', $meta).')';
        }

        if ('filters' === $type) {
            return $meta ? '('.implode(', ', $meta).')' : '';
        }

        return null;
    }
}
