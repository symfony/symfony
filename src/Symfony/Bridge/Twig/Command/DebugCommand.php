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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
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
    private $bundlesMetadata;
    private $twigDefaultPath;
    private $rootDir;

    public function __construct(Environment $twig, string $projectDir = null, array $bundlesMetadata = array(), string $twigDefaultPath = null, string $rootDir = null)
    {
        parent::__construct();

        $this->twig = $twig;
        $this->projectDir = $projectDir;
        $this->bundlesMetadata = $bundlesMetadata;
        $this->twigDefaultPath = $twigDefaultPath;
        $this->rootDir = $rootDir;
    }

    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'The template name'),
                new InputOption('filter', null, InputOption::VALUE_REQUIRED, 'Show details for all entries matching this filter'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (text or json)', 'text'),
            ))
            ->setDescription('Shows a list of twig functions, filters, globals and tests')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command outputs a list of twig functions,
filters, globals and tests.

  <info>php %command.full_name%</info>

The command lists all functions, filters, etc.

  <info>php %command.full_name% @Twig/Exception/error.html.twig</info>

The command lists all paths that match the given template name.

  <info>php %command.full_name% --filter=date</info>

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
        $name = $input->getArgument('name');
        $filter = $input->getOption('filter');

        if (null !== $name && !$this->twig->getLoader() instanceof FilesystemLoader) {
            throw new InvalidArgumentException(sprintf('Argument "name" not supported, it requires the Twig loader "%s"', FilesystemLoader::class));
        }

        switch ($input->getOption('format')) {
            case 'text':
                return $name ? $this->displayPathsText($io, $name) : $this->displayGeneralText($io, $filter);
            case 'json':
                return $name ? $this->displayPathsJson($io, $name) : $this->displayGeneralJson($io, $filter);
            default:
                throw new InvalidArgumentException(sprintf('The format "%s" is not supported.', $input->getOption('format')));
        }
    }

    private function displayPathsText(SymfonyStyle $io, string $name)
    {
        $files = $this->findTemplateFiles($name);
        $paths = $this->getLoaderPaths($name);

        $io->section('Matched File');
        if ($files) {
            $io->success(array_shift($files));

            if ($files) {
                $io->section('Overridden Files');
                $io->listing($files);
            }
        } else {
            $alternatives = array();

            if ($paths) {
                $shortnames = array();
                $dirs = array();
                foreach (current($paths) as $path) {
                    $dirs[] = $this->isAbsolutePath($path) ? $path : $this->projectDir.'/'.$path;
                }
                foreach (Finder::create()->files()->followLinks()->in($dirs) as $file) {
                    $shortnames[] = str_replace('\\', '/', $file->getRelativePathname());
                }

                list($namespace, $shortname) = $this->parseTemplateName($name);
                $alternatives = $this->findAlternatives($shortname, $shortnames);
                if (FilesystemLoader::MAIN_NAMESPACE !== $namespace) {
                    $alternatives = array_map(function ($shortname) use ($namespace) {
                        return '@'.$namespace.'/'.$shortname;
                    }, $alternatives);
                }
            }

            $this->error($io, sprintf('Template name "%s" not found', $name), $alternatives);
        }

        $io->section('Configured Paths');
        if ($paths) {
            $io->table(array('Namespace', 'Paths'), $this->buildTableRows($paths));
        } else {
            $alternatives = array();
            $namespace = $this->parseTemplateName($name)[0];

            if (FilesystemLoader::MAIN_NAMESPACE === $namespace) {
                $message = 'No template paths configured for your application';
            } else {
                $message = sprintf('No template paths configured for "@%s" namespace', $namespace);
                $namespaces = $this->twig->getLoader()->getNamespaces();
                foreach ($this->findAlternatives($namespace, $namespaces) as $namespace) {
                    $alternatives[] = '@'.$namespace;
                }
            }

            $this->error($io, $message, $alternatives);

            if (!$alternatives && $paths = $this->getLoaderPaths()) {
                $io->table(array('Namespace', 'Paths'), $this->buildTableRows($paths));
            }
        }
    }

    private function displayPathsJson(SymfonyStyle $io, string $name)
    {
        $files = $this->findTemplateFiles($name);
        $paths = $this->getLoaderPaths($name);

        if ($files) {
            $data['matched_file'] = array_shift($files);
            if ($files) {
                $data['overridden_files'] = $files;
            }
        } else {
            $data['matched_file'] = sprintf('Template name "%s" not found', $name);
        }
        $data['loader_paths'] = $paths;

        $io->writeln(json_encode($data));
    }

    private function displayGeneralText(SymfonyStyle $io, string $filter = null)
    {
        $types = array('functions', 'filters', 'tests', 'globals');
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

        if (!$filter && $paths = $this->getLoaderPaths()) {
            $io->section('Loader Paths');
            $io->table(array('Namespace', 'Paths'), $this->buildTableRows($paths));
        }

        if ($wronBundles = $this->findWrongBundleOverrides()) {
            foreach ($this->buildWarningMessages($wronBundles) as $message) {
                $io->warning($message);
            }
        }
    }

    private function displayGeneralJson(SymfonyStyle $io, $filter)
    {
        $types = array('functions', 'filters', 'tests', 'globals');
        $data = array();
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

        if (!$filter && $paths = $this->getLoaderPaths($filter)) {
            $data['loader_paths'] = $paths;
        }

        if ($wronBundles = $this->findWrongBundleOverrides()) {
            $data['warnings'] = $this->buildWarningMessages($wronBundles);
        }

        $io->writeln(json_encode($data));
    }

    private function getLoaderPaths(string $name = null): array
    {
        /** @var FilesystemLoader $loader */
        $loader = $this->twig->getLoader();
        $loaderPaths = array();
        $namespaces = $loader->getNamespaces();
        if (null !== $name) {
            $namespace = $this->parseTemplateName($name)[0];
            $namespaces = array_intersect(array($namespace), $namespaces);
        }

        foreach ($namespaces as $namespace) {
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
            return;
        }
        if ('functions' === $type || 'filters' === $type) {
            $cb = $entity->getCallable();
            if (null === $cb) {
                return;
            }
            if (\is_array($cb)) {
                if (!method_exists($cb[0], $cb[1])) {
                    return;
                }
                $refl = new \ReflectionMethod($cb[0], $cb[1]);
            } elseif (\is_object($cb) && method_exists($cb, '__invoke')) {
                $refl = new \ReflectionMethod($cb, '__invoke');
            } elseif (\function_exists($cb)) {
                $refl = new \ReflectionFunction($cb);
            } elseif (\is_string($cb) && preg_match('{^(.+)::(.+)$}', $cb, $m) && method_exists($m[1], $m[2])) {
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

            if ('filters' === $type) {
                // remove the value the filter is applied on
                array_shift($args);
            }

            // format args
            $args = array_map(function (\ReflectionParameter $param) {
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
        if ('tests' === $type) {
            return '';
        }

        try {
            $meta = $this->getMetadata($type, $entity);
            if (null === $meta) {
                return '(unknown?)';
            }
        } catch (\UnexpectedValueException $e) {
            return ' <error>'.$e->getMessage().'</error>';
        }

        if ('globals' === $type) {
            if (\is_object($meta)) {
                return ' = object('.\get_class($meta).')';
            }

            return ' = '.substr(@json_encode($meta), 0, 50);
        }

        if ('functions' === $type) {
            return '('.implode(', ', $meta).')';
        }

        if ('filters' === $type) {
            return $meta ? '('.implode(', ', $meta).')' : '';
        }
    }

    private function findWrongBundleOverrides(): array
    {
        $alternatives = array();
        $bundleNames = array();

        if ($this->rootDir && $this->projectDir) {
            $folders = glob($this->rootDir.'/Resources/*/views', GLOB_ONLYDIR);
            $relativePath = ltrim(substr($this->rootDir.'/Resources/', \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
            $bundleNames = array_reduce(
                $folders,
                function ($carry, $absolutePath) use ($relativePath) {
                    if (0 === strpos($absolutePath, $this->projectDir)) {
                        $name = basename(\dirname($absolutePath));
                        $path = $relativePath.$name;
                        $carry[$name] = $path;
                    }

                    return $carry;
                },
                $bundleNames
            );
        }

        if ($this->twigDefaultPath && $this->projectDir) {
            $folders = glob($this->twigDefaultPath.'/bundles/*', GLOB_ONLYDIR);
            $relativePath = ltrim(substr($this->twigDefaultPath.'/bundles', \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
            $bundleNames = array_reduce(
                $folders,
                function ($carry, $absolutePath) use ($relativePath) {
                    if (0 === strpos($absolutePath, $this->projectDir)) {
                        $path = ltrim(substr($absolutePath, \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
                        $name = ltrim(substr($path, \strlen($relativePath)), \DIRECTORY_SEPARATOR);
                        $carry[$name] = $path;
                    }

                    return $carry;
                },
                $bundleNames
            );
        }

        if (\count($bundleNames)) {
            $notFoundBundles = array_diff_key($bundleNames, $this->bundlesMetadata);
            if (\count($notFoundBundles)) {
                $alternatives = array();
                foreach ($notFoundBundles as $notFoundBundle => $path) {
                    $alternatives[$path] = array();
                    foreach ($this->bundlesMetadata as $name => $bundle) {
                        $lev = levenshtein($notFoundBundle, $name);
                        if ($lev <= \strlen($notFoundBundle) / 3 || false !== strpos($name, $notFoundBundle)) {
                            $alternatives[$path][] = $name;
                        }
                    }
                }
            }
        }

        return $alternatives;
    }

    private function buildWarningMessages(array $wrongBundles): array
    {
        $messages = array();
        foreach ($wrongBundles as $path => $alternatives) {
            $message = sprintf('Path "%s" not matching any bundle found', $path);
            if ($alternatives) {
                if (1 === \count($alternatives)) {
                    $message .= sprintf(", did you mean \"%s\"?\n", $alternatives[0]);
                } else {
                    $message .= ", did you mean one of these:\n";
                    foreach ($alternatives as $bundle) {
                        $message .= sprintf("  - %s\n", $bundle);
                    }
                }
            }
            $messages[] = trim($message);
        }

        return $messages;
    }

    private function error(SymfonyStyle $io, string $message, array $alternatives = array()): void
    {
        if ($alternatives) {
            if (1 === \count($alternatives)) {
                $message .= "\n\nDid you mean this?\n    ";
            } else {
                $message .= "\n\nDid you mean one of these?\n    ";
            }
            $message .= implode("\n    ", $alternatives);
        }

        $io->block($message, null, 'fg=white;bg=red', ' ', true);
    }

    private function findTemplateFiles(string $name): array
    {
        /** @var FilesystemLoader $loader */
        $loader = $this->twig->getLoader();
        $files = array();
        list($namespace, $shortname) = $this->parseTemplateName($name);

        foreach ($loader->getPaths($namespace) as $path) {
            if (!$this->isAbsolutePath($path)) {
                $path = $this->projectDir.'/'.$path;
            }
            $filename = $path.'/'.$shortname;

            if (is_file($filename)) {
                if (false !== $realpath = realpath($filename)) {
                    $files[] = $this->getRelativePath($realpath);
                } else {
                    $files[] = $this->getRelativePath($filename);
                }
            }
        }

        return $files;
    }

    private function parseTemplateName(string $name, string $default = FilesystemLoader::MAIN_NAMESPACE): array
    {
        if (isset($name[0]) && '@' === $name[0]) {
            if (false === ($pos = strpos($name, '/')) || $pos === \strlen($name) - 1) {
                throw new InvalidArgumentException(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name));
            }

            $namespace = substr($name, 1, $pos - 1);
            $shortname = substr($name, $pos + 1);

            return array($namespace, $shortname);
        }

        return array($default, $name);
    }

    private function buildTableRows(array $loaderPaths): array
    {
        $rows = array();
        $firstNamespace = true;
        $prevHasSeparator = false;

        foreach ($loaderPaths as $namespace => $paths) {
            if (!$firstNamespace && !$prevHasSeparator && \count($paths) > 1) {
                $rows[] = array('', '');
            }
            $firstNamespace = false;
            foreach ($paths as $path) {
                $rows[] = array($namespace, $path.\DIRECTORY_SEPARATOR);
                $namespace = '';
            }
            if (\count($paths) > 1) {
                $rows[] = array('', '');
                $prevHasSeparator = true;
            } else {
                $prevHasSeparator = false;
            }
        }
        if ($prevHasSeparator) {
            array_pop($rows);
        }

        return $rows;
    }

    private function findAlternatives(string $name, array $collection): array
    {
        $alternatives = array();
        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || false !== strpos($item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }

        $threshold = 1e3;
        $alternatives = array_filter($alternatives, function ($lev) use ($threshold) { return $lev < 2 * $threshold; });
        ksort($alternatives, SORT_NATURAL | SORT_FLAG_CASE);

        return array_keys($alternatives);
    }

    private function getRelativePath(string $path): string
    {
        if (null !== $this->projectDir && 0 === strpos($path, $this->projectDir)) {
            return ltrim(substr($path, \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    private function isAbsolutePath(string $file): bool
    {
        return strspn($file, '/\\', 0, 1) || (\strlen($file) > 3 && ctype_alpha($file[0]) && ':' === $file[1] && strspn($file, '/\\', 2, 1)) || null !== parse_url($file, PHP_URL_SCHEME);
    }
}
