<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\Serializer\Dumper\NormalizerDumper;

/**
 * Normalize objects using generated normalizers.
 *
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
class GeneratedObjectNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private $normalizerDumper;
    private $cacheDir;
    private $debug;

    /**
     * @var NormalizerInterface[]
     */
    private $normalizers = array();

    /**
     * @var ConfigCacheFactoryInterface|null
     */
    private $configCacheFactory;

    /**
     * @var int
     */
    protected $circularReferenceLimit = 1;

    /**
     * @var callable|null
     */
    protected $circularReferenceHandler;

    public function __construct(NormalizerDumper $normalizerDumper, string $cacheDir, bool $debug)
    {
        $this->normalizerDumper = $normalizerDumper;
        $this->cacheDir = $cacheDir;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $class = get_class($object);
        if (isset($this->normalizers[$class])) {
            return $this->normalizers[$class]->normalize($object, $format, $context);
        }

        $normalizerClass = 'Symfony\Component\Serializer\Normalizer\Generated\\'.$class.'Normalizer';
        $cache = $this->getConfigCacheFactory()->cache($this->cacheDir.'/normalizers-'.str_replace('\\', '-', $class).'.php',
            function (ConfigCacheInterface $cache) use ($class, $normalizerClass) {
                $pos = strrpos($normalizerClass, '\\');
                $code = $this->normalizerDumper->dump($class, array(
                    'class' => substr($normalizerClass, $pos + 1),
                    'namespace' => substr($normalizerClass, 0, $pos),
                ));

                $cache->write($code, array(new ReflectionClassResource(new \ReflectionClass($class))));
            }
        );

        require_once $cache->getPath();

        $this->normalizers[$class] = $normalizer = new $normalizerClass();
        $normalizer->setNormalizer($this->normalizer);
        $normalizer->setCircularReferenceLimit($this->circularReferenceLimit);
        if (null !== $this->circularReferenceHandler) {
            $normalizer->setCircularReferenceHandler($this->circularReferenceHandler);
        }

        return $normalizer->normalize($object, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return \is_object($data) && !$data instanceof \Traversable;
    }

    /**
     * Set circular reference limit.
     *
     * @param int $circularReferenceLimit Limit of iterations for the same object
     *
     * @return self
     */
    public function setCircularReferenceLimit($circularReferenceLimit)
    {
        $this->circularReferenceLimit = $circularReferenceLimit;

        return $this;
    }

    /**
     * Set circular reference handler.
     *
     * @param callable $circularReferenceHandler
     *
     * @return self
     */
    public function setCircularReferenceHandler(callable $circularReferenceHandler)
    {
        $this->circularReferenceHandler = $circularReferenceHandler;

        return $this;
    }

    private function generateUniqueName($class)
    {
        return str_replace('\\', '-', $class);
    }

    private function getConfigCacheFactory(): ConfigCacheFactoryInterface
    {
        if (null === $this->configCacheFactory) {
            $this->configCacheFactory = new ConfigCacheFactory($this->debug);
        }

        return $this->configCacheFactory;
    }
}
