ProxyManager Bridge
===================

Provides integration for [ProxyManager][1] with various Symfony2 components.

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Bridge/ProxyManager/
    $ composer.phar install --dev
    $ phpunit

[1]: https://github.com/Ocramius/ProxyManager

How to use Lazy Loading
---------

Example code based in the one used in Drupal Symfony Inject:

    if ($definition->isLazy()) {
      $set_proxy_instantiator = TRUE;
      $container_builder->setProxyInstantiator(new CachedInstantiator($proxies_path));
      $definition->setLazy(true);
    }
