<?php
/**
 * @file
 * CachedInstantiator class.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\Instantiator;

use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;

/**
 * Lazy loading proxy generator (Using cached Proxy to improve performance).
 */
class CachedInstantiator implements InstantiatorInterface
{
  /**
   * @var \ProxyManager\Factory\LazyLoadingValueHolderFactory
   */
  private $factory;

  /**
   * Constructor
   */
  public function __construct($proxies_path)
  {
    $config = new Configuration();
    $config->setProxiesTargetDir($proxies_path);
    $fileLocator = new FileLocator($config->getProxiesTargetDir());
    $config->setGeneratorStrategy(new FileWriterGeneratorStrategy($fileLocator));

    $this->factory = new LazyLoadingValueHolderFactory($config);
  }

  /**
   * {@inheritdoc}
   */
  public function instantiateProxy(ContainerInterface $container, Definition $definition, $id, $realInstantiator)
  {
    return $this->factory->createProxy(
      $definition->getClass(),
      function (&$wrappedInstance, LazyLoadingInterface $proxy) use ($realInstantiator) {
        $wrappedInstance = call_user_func($realInstantiator);

        $proxy->setProxyInitializer(null);

        return true;
      }
    );
  }
}
