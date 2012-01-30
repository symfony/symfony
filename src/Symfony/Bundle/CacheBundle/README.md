DoctrineCacheBundle
===================

This Bundle provides integration into Symfony2 with the Doctrine Common Cache layer

Installation
============

    1. Add this bundle to your project as a Git submodule:

        $ git submodule add git://github.com/liip/LiipDoctrineCacheBundle.git vendor/bundles/Liip/DoctrineCacheBundle

    2. Add the Liip namespace to your autoloader:

        // app/autoload.php
        $loader->registerNamespaces(array(
            'Liip' => __DIR__.'/../vendor/bundles',
            // your other namespaces
        ));

    3. Add this bundle to your application's kernel:

        // application/ApplicationKernel.php
        public function registerBundles()
        {
          return array(
              // ...
              new Symfony\Bundle\CacheBundle\LiipDoctrineCacheBundle(),
              // ...
          );
        }

Configuration
=============

Simply configure any number of cache services:

    # app/config.yml
    liip_doctrine_cache:
        namespaces:
            # name of the service (aka liip_doctrine_cache.ns.foo)
            foo:
                # cache namespace is "ding"
                namespace: ding
                # cache type is "apc"
                type: apc
            # name of the service (aka liip_doctrine_cache.ns.foo) and namespace
            lala:
                # cache type is "apc"
                type: apc
            # name of the service (aka liip_doctrine_cache.ns.bar)
            bar:
                # cache namespace is "ding"
                namespace: ding
                # cache type is "memcached"
                type: memcached
                # name of a service of class Memcached that is fully configured
                id: my_memcached_service

Custom cache types
==================

Simply define a new type my defining a service named `liip_doctrine_cache.[type name]`.
Note the service needs to implement ``Doctrine\Common\Cache\Cache`` interface.
