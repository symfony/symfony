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
        'cache_vary' => [],
    ];

    /**
     * @var array
     */
    private $resourceLocales;

    /**
     * Holds parameters from addResource() calls so we can defer the actual
     * parent::addResource() calls until initialize() is executed.
     *
     * @var array
     */
    private $resources = [];

    private $resourceFiles;

    /**
     * @var string[]
     */
    private $scannedDirectories;

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
        $this->resourceLocales = array_keys($this->options['resource_files']);
        $this->resourceFiles = $this->options['resource_files'];
        $this->scannedDirectories = $this->options['scanned_directories'];

        parent::__construct($defaultLocale, $formatter, $this->options['cache_dir'], $this->options['debug'], $this->options['cache_vary']);
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

        $locales = array_merge($this->getFallbackLocales(), [$this->getLocale()], $this->resourceLocales);
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
        if ($this->resourceFiles) {
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

    /**
     * @internal
     */
    protected function doLoadCatalogue(string $locale): void
    {
        parent::doLoadCatalogue($locale);

        foreach ($this->scannedDirectories as $directory) {
            $resourceClass = file_exists($directory) ? DirectoryResource::class : FileExistenceResource::class;
            $this->catalogues[$locale]->addResource(new $resourceClass($directory));
        }
    }

    protected function initialize()
    {
        if ($this->resourceFiles) {
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

        foreach ($filesByLocale as $locale => $files) {
            foreach ($files as $key => $file) {
                // filename is domain.locale.format
                $fileNameParts = explode('.', basename($file));
                $format = array_pop($fileNameParts);
                $locale = array_pop($fileNameParts);
                $domain = implode('.', $fileNameParts);
                $this->addResource($format, $file, $locale, $domain);
            }
        }
    }
}
