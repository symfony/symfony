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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * Lists twig functions, filters, globals and tests present in the current project.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
#[AsCommand(name: 'debug:twig', description: 'Show a list of twig functions, filters, globals and tests')]
class DebugCommand extends Command
{
    private Environment $twig;
    private ?string $projectDir;
    private array $bundlesMetadata;
    private ?string $twigDefaultPath;

    /**
     * @var FilesystemLoader[]
     */
    private array $filesystemLoaders;

    private ?FileLinkFormatter $fileLinkFormatter;

    public function __construct(Environment $twig, string $projectDir = null, array $bundlesMetadata = [], string $twigDefaultPath = null, FileLinkFormatter $fileLinkFormatter = null)
    {
        parent::__construct();

        $this->twig = $twig;
        $this->projectDir = $projectDir;
        $this->bundlesMetadata = $bundlesMetadata;
        $this->twigDefaultPath = $twigDefaultPath;
        $this->fileLinkFormatter = $fileLinkFormatter;
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'The template name'),
                new InputOption('filter', null, InputOption::VALUE_REQUIRED, 'Show details for all entries matching this filter'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (text or json)', 'text'),
            ])
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $filter = $input->getOption('filter');

        if (null !== $name && [] === $this->getFilesystemLoaders()) {
            throw new InvalidArgumentException(sprintf('Argument "name" not supported, it requires the Twig loader "%s".', FilesystemLoader::class));
        }

        switch ($input->getOption('format')) {
            case 'text':
                $name ? $this->displayPathsText($io, $name) : $this->displayGeneralText($io, $filter);
                break;
            case 'json':
                $name ? $this->displayPathsJson($io, $name) : $this->displayGeneralJson($io, $filter);
                break;
            default:
                throw new InvalidArgumentException(sprintf('The format "%s" is not supported.', $input->getOption('format')));
        }

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(array_keys($this->getLoaderPaths()));
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues(['text', 'json']);
        }
    }

    private function displayPathsText(SymfonyStyle $io, string $name)
    {
        $file = new \ArrayIterator($this->findTemplateFiles($name));
        $paths = $this->getLoaderPaths($name);

        $io->section('Matched File');
        if ($file->valid()) {
            if ($fileLink = $this->getFileLink($file->key())) {
                $io->block($file->current(), 'OK', sprintf('fg=black;bg=green;href=%s', $fileLink), ' ', true);
            } else {
                $io->success($file->current());
            }
            $file->next();

            if ($file->valid()) {
                $io->section('Overridden Files');
                do {
                    if ($fileLink = $this->getFileLink($file->key())) {
                        $io->text(sprintf('* <href=%s>%s</>', $fileLink, $file->current()));
                    } else {
                        $io->text(sprintf('* %s', $file->current()));
                    }
                    $file->next();
                } while ($file->valid());
            }
        } else {
            $alternatives = [];

            if ($paths) {
                $shortnames = [];
                $dirs = [];
                foreach (current($paths) as $path) {
                    $dirs[] = $this->isAbsolutePath($path) ? $path : $this->projectDir.'/'.$path;
                }
                foreach (Finder::create()->files()->followLinks()->in($dirs) as $file) {
                    $shortnames[] = str_replace('\\', '/', $file->getRelativePathname());
                }

                [$namespace, $shortname] = $this->parseTemplateName($name);
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
            $io->table(['Namespace', 'Paths'], $this->buildTableRows($paths));
        } else {
            $alternatives = [];
            $namespace = $this->parseTemplateName($name)[0];

            if (FilesystemLoader::MAIN_NAMESPACE === $namespace) {
                $message = 'No template paths configured for your application';
            } else {
                $message = sprintf('No template paths configured for "@%s" namespace', $namespace);
                foreach ($this->getFilesystemLoaders() as $loader) {
                    $namespaces = $loader->getNamespaces();
                    foreach ($this->findAlternatives($namespace, $namespaces) as $namespace) {
                        $alternatives[] = '@'.$namespace;
                    }
                }
            }

            $this->error($io, $message, $alternatives);

            if (!$alternatives && $paths = $this->getLoaderPaths()) {
                $io->table(['Namespace', 'Paths'], $this->buildTableRows($paths));
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
        $decorated = $io->isDecorated();
        $types = ['functions', 'filters', 'tests', 'globals'];
        foreach ($types as $index => $type) {
            $items = [];
            foreach ($this->twig->{'get'.ucfirst($type)}() as $name => $entity) {
                if (!$filter || str_contains($name, $filter)) {
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

        if (!$filter && $paths = $this->getLoaderPaths()) {
            $io->section('Loader Paths');
            $io->table(['Namespace', 'Paths'], $this->buildTableRows($paths));
        }

        if ($wrongBundles = $this->findWrongBundleOverrides()) {
            foreach ($this->buildWarningMessages($wrongBundles) as $message) {
                $io->warning($message);
            }
        }
    }

    private function displayGeneralJson(SymfonyStyle $io, ?string $filter)
    {
        $decorated = $io->isDecorated();
        $types = ['functions', 'filters', 'tests', 'globals'];
        $data = [];
        foreach ($types as $type) {
            foreach ($this->twig->{'get'.ucfirst($type)}() as $name => $entity) {
                if (!$filter || str_contains($name, $filter)) {
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

        if ($wrongBundles = $this->findWrongBundleOverrides()) {
            $data['warnings'] = $this->buildWarningMessages($wrongBundles);
        }

        $data = json_encode($data, \JSON_PRETTY_PRINT);
        $io->writeln($decorated ? OutputFormatter::escape($data) : $data);
    }

    private function getLoaderPaths(string $name = null): array
    {
        $loaderPaths = [];
        foreach ($this->getFilesystemLoaders() as $loader) {
            $namespaces = $loader->getNamespaces();
            if (null !== $name) {
                $namespace = $this->parseTemplateName($name)[0];
                $namespaces = array_intersect([$namespace], $namespaces);
            }

            foreach ($namespaces as $namespace) {
                $paths = array_map($this->getRelativePath(...), $loader->getPaths($namespace));

                if (FilesystemLoader::MAIN_NAMESPACE === $namespace) {
                    $namespace = '(None)';
                } else {
                    $namespace = '@'.$namespace;
                }

                $loaderPaths[$namespace] = array_merge($loaderPaths[$namespace] ?? [], $paths);
            }
        }

        return $loaderPaths;
    }

    private function getMetadata(string $type, mixed $entity)
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
            $args = array_map(function (\ReflectionParameter $param) {
                if ($param->isDefaultValueAvailable()) {
                    return $param->getName().' = '.json_encode($param->getDefaultValue());
                }

                return $param->getName();
            }, $args);

            return $args;
        }

        return null;
    }

    private function getPrettyMetadata(string $type, mixed $entity, bool $decorated): ?string
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

    private function findWrongBundleOverrides(): array
    {
        $alternatives = [];
        $bundleNames = [];

        if ($this->twigDefaultPath && $this->projectDir) {
            $folders = glob($this->twigDefaultPath.'/bundles/*', \GLOB_ONLYDIR);
            $relativePath = ltrim(substr($this->twigDefaultPath.'/bundles/', \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
            $bundleNames = array_reduce($folders, function ($carry, $absolutePath) use ($relativePath) {
                if (str_starts_with($absolutePath, $this->projectDir)) {
                    $name = basename($absolutePath);
                    $path = ltrim($relativePath.$name, \DIRECTORY_SEPARATOR);
                    $carry[$name] = $path;
                }

                return $carry;
            }, $bundleNames);
        }

        if ($notFoundBundles = array_diff_key($bundleNames, $this->bundlesMetadata)) {
            $alternatives = [];
            foreach ($notFoundBundles as $notFoundBundle => $path) {
                $alternatives[$path] = $this->findAlternatives($notFoundBundle, array_keys($this->bundlesMetadata));
            }
        }

        return $alternatives;
    }

    private function buildWarningMessages(array $wrongBundles): array
    {
        $messages = [];
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

    private function error(SymfonyStyle $io, string $message, array $alternatives = []): void
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
        [$namespace, $shortname] = $this->parseTemplateName($name);

        $files = [];
        foreach ($this->getFilesystemLoaders() as $loader) {
            foreach ($loader->getPaths($namespace) as $path) {
                if (!$this->isAbsolutePath($path)) {
                    $path = $this->projectDir.'/'.$path;
                }
                $filename = $path.'/'.$shortname;

                if (is_file($filename)) {
                    if (false !== $realpath = realpath($filename)) {
                        $files[$realpath] = $this->getRelativePath($realpath);
                    } else {
                        $files[$filename] = $this->getRelativePath($filename);
                    }
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

            return [$namespace, $shortname];
        }

        return [$default, $name];
    }

    private function buildTableRows(array $loaderPaths): array
    {
        $rows = [];
        $firstNamespace = true;
        $prevHasSeparator = false;

        foreach ($loaderPaths as $namespace => $paths) {
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

        return $rows;
    }

    private function findAlternatives(string $name, array $collection): array
    {
        $alternatives = [];
        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || str_contains($item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }

        $threshold = 1e3;
        $alternatives = array_filter($alternatives, function ($lev) use ($threshold) { return $lev < 2 * $threshold; });
        ksort($alternatives, \SORT_NATURAL | \SORT_FLAG_CASE);

        return array_keys($alternatives);
    }

    private function getRelativePath(string $path): string
    {
        if (null !== $this->projectDir && str_starts_with($path, $this->projectDir)) {
            return ltrim(substr($path, \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    private function isAbsolutePath(string $file): bool
    {
        return strspn($file, '/\\', 0, 1) || (\strlen($file) > 3 && ctype_alpha($file[0]) && ':' === $file[1] && strspn($file, '/\\', 2, 1)) || null !== parse_url($file, \PHP_URL_SCHEME);
    }

    /**
     * @return FilesystemLoader[]
     */
    private function getFilesystemLoaders(): array
    {
        if (isset($this->filesystemLoaders)) {
            return $this->filesystemLoaders;
        }
        $this->filesystemLoaders = [];

        $loader = $this->twig->getLoader();
        if ($loader instanceof FilesystemLoader) {
            $this->filesystemLoaders[] = $loader;
        } elseif ($loader instanceof ChainLoader) {
            foreach ($loader->getLoaders() as $l) {
                if ($l instanceof FilesystemLoader) {
                    $this->filesystemLoaders[] = $l;
                }
            }
        }

        return $this->filesystemLoaders;
    }

    private function getFileLink(string $absolutePath): string
    {
        if (null === $this->fileLinkFormatter) {
            return '';
        }

        return (string) $this->fileLinkFormatter->format($absolutePath, 1);
    }
}
