Asset Component
===============

The Asset component manages asset URLs.

Versioned Asset URLs
--------------------

The basic `Package` adds a version to generated asset URLs:

```php
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

$package = new Package(new StaticVersionStrategy('v1'));

echo $package->getUrl('/me.png');
// /me.png?v1
```

The default format can be configured:

```php
$package = new Package(new StaticVersionStrategy('v1', '%s?version=%s'));

echo $package->getUrl('/me.png');
// /me.png?version=v1

// put the version before the path
$package = new Package(new StaticVersionStrategy('v1', 'version-%2$s/%1$s'));

echo $package->getUrl('/me.png');
// /version-v1/me.png
```

Asset URLs Base Path
--------------------

When all assets are stored in a common path, use the `PathPackage` to avoid
repeating yourself:

```php
use Symfony\Component\Asset\PathPackage;

$package = new PathPackage('/images', new StaticVersionStrategy('v1'));

echo $package->getUrl('/me.png');
// /images/me.png?v1
```

Asset URLs Base URLs
--------------------

If your assets are hosted on different domain name than the main website, use
the `UrlPackage` class:

```php
use Symfony\Component\Asset\UrlPackage;

$package = new UrlPackage('http://assets.example.com/images/', new StaticVersionStrategy('v1'));

echo $package->getUrl('/me.png');
// http://assets.example.com/images/me.png?v1
```

One technique used to speed up page rendering in browsers is to use several
domains for assets; this is possible by passing more than one base URLs:

```php
use Symfony\Component\Asset\UrlPackage;

$urls = array(
    'http://a1.example.com/images/',
    'http://a2.example.com/images/',
);
$package = new UrlPackage($urls, new StaticVersionStrategy('v1'));

echo $package->getUrl('/me.png');
// http://a1.example.com/images/me.png?v1
```

Note that it's also guaranteed that any given path will always use the same
base URL to be nice with HTTP caching mechanisms.

HttpFoundation Integration
--------------------------

If you are using HttpFoundation for your project, set the Context to get
additional features for free:

```php
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\Context\RequestStackContext;

$package = new PathPackage('images', new StaticVersionStrategy('v1'));
$package->setContext(new RequestStackContext($requestStack));

echo $package->getUrl('/me.png');
// /somewhere/images/me.png?v1
```

In addition to the configured base path, `PathPackage` now also automatically
prepends the current request base URL to assets to allow your website to be
hosted anywhere under the web server root directory.

```php
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\Context\RequestStackContext;

$package = new UrlPackage(array('http://example.com/', 'https://example.com/'), new StaticVersionStrategy('v1'));
$package->setContext(new RequestStackContext($requestStack));

echo $package->getUrl('/me.png');
// https://example.com/images/me.png?v1
```

`UrlPackage` now uses the current request scheme (HTTP or HTTPs) to select an
appropriate base URL (HTTPs or protocol-relative URLs for HTTPs requests, any
base URL for HTTP requests).

Named Packages
--------------

The `Packages` class allows to easily manages several packages in a single
project by naming packages:

```php
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\Packages;

// by default, just add a version to all assets
$versionStrategy = new StaticVersionStrategy('v1');
$defaultPackage = new Asset\Package($versionStrategy);

$namedPackages = array(
    // images are hosted on another web server
    'img' => new Asset\UrlPackage('http://img.example.com/', $versionStrategy),

    // documents are stored deeply under the web root directory
    // let's create a shortcut
    'doc' => new Asset\PathPackage('/somewhere/deep/for/documents', $versionStrategy),
);

// bundle all packages to make it easy to use them
$packages = new Asset\Packages($defaultPackage, $namedPackages);

echo $packages->getUrl('/some.css');
// /some.css?v1

echo $packages->getUrl('/me.png', 'img');
// http://img.example.com/me.png?v1

echo $packages->getUrl('/me.pdf', 'doc');
// /somewhere/deep/for/documents/me.pdf?v1
```

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Asset/
    $ composer update
    $ phpunit
