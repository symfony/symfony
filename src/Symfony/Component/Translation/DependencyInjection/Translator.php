<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Resource\ComposerResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\Translator as BaseTranslator;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Translator extends BaseTranslator implements WarmableInterface
{
    protected array $options = [
        'cache_dir' => null,
        'debug' => false,
        'resource_files' => [],
        'scanned_directories' => [],
        'cache_vary' => [],
    ];

    /**
     * @var list<string>
     */
    private array $resourceLocales;

    /**
     * Holds parameters from addResource() calls so we can defer the actual
     * parent::addResource() calls until initialize() is executed.
     *
     * @var array[]
     */
    private array $resources = [];

    /**
     * @var string[][]
     */
    private array $resourceFiles;

    /**
     * @var string[]
     */
    private array $scannedDirectories;

    /**
     * @var string[]
     */
    private array $vendors;

    /**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir:      The cache directory (or null to disable caching)
     *   * debug:          Whether to enable debugging or not (false by default)
     *   * resource_files: List of translation resources available grouped by locale.
     *   * cache_vary:     An array of data that is serialized to generate the cached catalogue name.
     *
     * @param string[] $enabledLocales
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        protected ContainerInterface $container,
        MessageFormatterInterface $formatter,
        string $defaultLocale,
        protected array $loaderIds = [],
        array $options = [],
        private array $enabledLocales = [],
    ) {
        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new InvalidArgumentException(\sprintf('The Translator does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);
        $this->resourceLocales = array_keys($this->options['resource_files']);
        $this->resourceFiles = $this->options['resource_files'];
        $this->scannedDirectories = $this->options['scanned_directories'];

        parent::__construct($defaultLocale, $formatter, $this->options['cache_dir'], $this->options['debug'], $this->options['cache_vary']);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        // skip warmUp when translator doesn't use cache
        if (null === $this->options['cache_dir']) {
            return [];
        }

        $localesToWarmUp = $this->enabledLocales ?: array_merge($this->getFallbackLocales(), [$this->getLocale()], $this->resourceLocales);

        foreach (array_unique($localesToWarmUp) as $locale) {
            // reset catalogue in case it's already loaded during the dump of the other locales.
            if (isset($this->catalogues[$locale])) {
                unset($this->catalogues[$locale]);
            }

            $this->loadCatalogue($locale);
        }

        return [];
    }

    public function addResource(string $format, mixed $resource, string $locale, ?string $domain = null): void
    {
        if ($this->resourceFiles) {
            $this->addResourceFiles();
        }
        $this->resources[] = [$format, $resource, $locale, $domain];
    }

    protected function initializeCatalogue(string $locale): void
    {
        $this->initialize();
        parent::initializeCatalogue($locale);
    }

    /**
     * @internal
     */
    protected function doLoadCatalogue(string $locale): void
    {
        parent::doLoadCatalogue($locale);

        foreach ($this->scannedDirectories as $directory) {
            if (null !== $vendor = $this->findVendor($directory)) {
                // the directories of packages only change when installing them, which updates installed.json
                $resource = new FileResource($vendor.'/composer/installed.json');
            } elseif (file_exists($directory)) {
                $resource = new DirectoryResource($directory);
            } else {
                $resource = new FileExistenceResource($directory);
            }

            $this->catalogues[$locale]->addResource($resource);
        }
    }

    protected function initialize(): void
    {
        if ($this->resourceFiles) {
            $this->addResourceFiles();
        }
        foreach ($this->resources as $params) {
            [$format, $resource, $locale, $domain] = $params;
            parent::addResource($format, $resource, $locale, $domain);
        }
        $this->resources = [];

        foreach ($this->loaderIds as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->addLoader($alias, $this->container->get($id));
            }
        }
    }

    private function addResourceFiles(): void
    {
        $filesByLocale = $this->resourceFiles;
        $this->resourceFiles = [];

        foreach ($filesByLocale as $files) {
            foreach ($files as $file) {
                // filename is domain.locale.format
                $fileNameParts = explode('.', basename($file));
                $format = array_pop($fileNameParts);
                $locale = array_pop($fileNameParts);
                $domain = implode('.', $fileNameParts);
                $this->addResource($format, $file, $locale, $domain);
            }
        }
    }

    private function findVendor(string $directory): ?string
    {
        $this->vendors ??= (new ComposerResource())->getVendors();
        $directory = realpath($directory) ?: $directory;

        foreach ($this->vendors as $vendor) {
            if (\in_array($directory[\strlen($vendor)] ?? '', ['/', \DIRECTORY_SEPARATOR], true) && str_starts_with($directory, $vendor)) {
                return $vendor;
            }
        }

        return null;
    }
}
