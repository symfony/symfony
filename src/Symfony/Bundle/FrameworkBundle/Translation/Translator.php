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

use Symfony\Component\Translation\Translator as BaseTranslator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator extends BaseTranslator
{
    protected $container;
    protected $loaderIds;
    protected $resourceDirs;

    protected $options = array(
        'cache_dir' => null,
        'debug' => false,
    );

    /**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir: The cache directory (or null to disable caching)
     *   * debug:     Whether to enable debugging or not (false by default)
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param MessageSelector    $selector  The message selector for pluralization
     * @param array              $loaderIds An array of loader Ids
     * @param array              $resourceDirs An array of resource directories
     * @param array              $options   An array of options
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(ContainerInterface $container, MessageSelector $selector, $loaderIds = array(), $resourceDirs = array(), array $options = array())
    {
        $this->container = $container;
        $this->loaderIds = $loaderIds;
        $this->resourceDirs = $resourceDirs;

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Translator does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        parent::__construct(null, $selector, $this->options['cache_dir'], $this->options['debug']);
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeCatalogue($locale)
    {
        $this->initialize();
        parent::initializeCatalogue($locale);
    }

    protected function initialize()
    {
        foreach ($this->loaderIds as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->addLoader($alias, $this->container->get($id));
            }
        }

        if ($this->resourceDirs) {
            $finder = Finder::create()
                ->files()
                ->filter(function (\SplFileInfo $file) {
                    return 2 === substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
                })
                ->in($this->resourceDirs)
            ;

            foreach ($finder as $file) {
                // filename is domain.locale.format
                list($domain, $locale, $format) = explode('.', $file->getBasename(), 3);
                $this->addResource($format, (string) $file, $locale, $domain);
            }
        }
    }
}
