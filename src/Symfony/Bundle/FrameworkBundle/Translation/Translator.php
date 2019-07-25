<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\Translator as BaseTranslator;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator extends BaseTranslator implements WarmableInterface
{
    protected $container;

    protected $loaderIds;

    protected $options = [
        'cache_dir' => null,
        'debug' => false,
        'resource_files' => [],
        'scanned_directories' => [],
        'paths' => [],
    ];

    /**
     * Holds parameters from addResource() calls so we can defer the actual
     * parent::addResource() calls until initialize() is executed.
     *
     * @var array
     */
    private $resources = [];

    private $resourceFiles;
    private $trackedResources = [];

    /**
     * @var string[]
     */
    private $scannedDirectories;

    /**
     * @var string[]
     */
    private $paths;

    /**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir: The cache directory (or null to disable caching)
     *   * debug:     Whether to enable debugging or not (false by default)
     *
     * @param ContainerInterface        $container     A ContainerInterface instance
     * @param MessageFormatterInterface $formatter     The message formatter
     * @param string                    $defaultLocale
     * @param array                     $loaderIds     An array of loader Ids
     * @param array                     $options       An array of options
     *
     * @throws InvalidArgumentException
     */
    public function __construct(ContainerInterface $container, MessageFormatterInterface $formatter, string $defaultLocale, array $loaderIds = [], array $options = [])
    {
        $this->container = $container;
        $this->loaderIds = $loaderIds;

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new InvalidArgumentException(sprintf('The Translator does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);
        $this->resourceFiles = $this->options['resource_files'];

        if ($this->resourceFiles) {
            @trigger_error(sprintf('Passing the "resource_files" option to "%s" is deprecated since version 4.4 and will be unsupported in version 5. Pass a list of directories instead in the "paths" option.', __CLASS__), E_USER_DEPRECATED);
        }

        $this->scannedDirectories = $this->options['scanned_directories'];
        $this->paths = $this->options['paths'];

        parent::__construct($defaultLocale, $formatter, $this->options['cache_dir'], $this->options['debug']);
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        // skip warmUp when translator doesn't use cache
        if (null === $this->options['cache_dir']) {
            return;
        }

        $resourceLocales = [];

        if ($this->paths) {
            $resourceLocales = iterator_to_array($this->getResourceLocales());
        }

        $resourceLocales += array_keys($this->resourceFiles);

        $locales = array_merge($this->getFallbackLocales(), [$this->getLocale()], $resourceLocales);
        foreach (array_unique($locales) as $locale) {
            // reset catalogue in case it's already loaded during the dump of the other locales.
            if (isset($this->catalogues[$locale])) {
                unset($this->catalogues[$locale]);
            }

            $this->loadCatalogue($locale);
        }
    }

    public function addResource($format, $resource, $locale, $domain = null)
    {
        if ($this->resourceFiles || $this->paths) {
            $this->addResourceFiles();
        }
        $this->resources[] = [$format, $resource, $locale, $domain];
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeCatalogue($locale)
    {
        $this->initialize();
        parent::initializeCatalogue($locale);
    }

    protected function doLoadCatalogue($locale): void
    {
        parent::doLoadCatalogue($locale);

        foreach (array_unique(array_merge($this->scannedDirectories, $this->paths)) as $directory) {
            $resourceClass = file_exists($directory) ? DirectoryResource::class : FileExistenceResource::class;
            $this->catalogues[$locale]->addResource(new $resourceClass($directory));
        }
    }

    protected function initialize()
    {
        if ($this->resourceFiles || $this->paths) {
            $this->addResourceFiles();
        }
        foreach ($this->resources as $key => $params) {
            list($format, $resource, $locale, $domain) = $params;
            parent::addResource($format, $resource, $locale, $domain);
        }
        $this->resources = [];

        foreach ($this->loaderIds as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->addLoader($alias, $this->container->get($id));
            }
        }
    }

    private function addResourceFiles()
    {
        $filesByLocale = $this->resourceFiles;
        $this->resourceFiles = [];

        if ($this->paths) {
            foreach ($this->getResourceFiles() as $locale => $file) {
                $this->trackedResources[$file] = true;

                // filename is domain.locale.format
                $fileNameParts = explode('.', basename($file));
                $format = array_pop($fileNameParts);
                $locale = array_pop($fileNameParts);
                $domain = implode('.', $fileNameParts);
                $this->addResource($format, $file, $locale, $domain);
            }
        }

        foreach ($filesByLocale as $locale => $files) {
            foreach ($files as $file) {
                if (isset($this->trackedResources[$file])) {
                    continue;
                }

                $fileNameParts = explode('.', basename($file));
                $format = array_pop($fileNameParts);
                $locale = array_pop($fileNameParts);
                $domain = implode('.', $fileNameParts);

                $this->addResource($format, $file, $locale, $domain);
            }
        }
    }

    private function getResourceLocales()
    {
        foreach ($this->findResources() as $file) {
            $fileNameParts = explode('.', basename($file));
            $locale = $fileNameParts[\count($fileNameParts) - 2];

            yield $locale;
        }
    }

    private function getResourceFiles()
    {
        $finder = $this->findResources();

        $this->paths = [];

        foreach ($finder as $file) {
            $fileNameParts = explode('.', basename($file));
            $locale = $fileNameParts[\count($fileNameParts) - 2];

            yield $locale => (string) $file;
        }
    }

    private function findResources()
    {
        return $finder = Finder::create()
            ->followLinks()
            ->files()
            ->filter(function (\SplFileInfo $file) {
                return 2 <= substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
            })
            ->in($this->paths)
            ->sortByName();
    }
}
