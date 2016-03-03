Finder Component
================

Finder finds files and directories via an intuitive fluent interface.

```php
use Symfony\Component\Finder\Finder;

$finder = new Finder();

$iterator = $finder
  ->files()
  ->name('*.php')
  ->depth(0)
  ->size('>= 1K')
  ->in(__DIR__);

foreach ($iterator as $file) {
    print $file->getRealpath()."\n";
}
```

The iterator returns instances of [Symfony\Component\Finder\SplFileInfo\SplFileInfo][1].
Besides the build-in methods inherited from [\SplFileInfo][2] (`getPerms()`, `getSize()`, ...),
you can also use `getRelativePath()` and `getRelativePathname()`. Read the
[official documentation][3] for more information.

But you can also use it to find files stored remotely like in this example where
we are looking for files on Amazon S3:

```php
$s3 = new \Zend_Service_Amazon_S3($key, $secret);
$s3->registerStreamWrapper("s3");

$finder = new Finder();
$finder->name('photos*')->size('< 100K')->date('since 1 hour ago');
foreach ($finder->in('s3://bucket-name') as $file) {
    print $file->getFilename()."\n";
}
```

Resources
---------

  * [Documentation](https://symfony.com/doc/current/components/finder.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: http://api.symfony.com/2.5/Symfony/Component/Finder/SplFileInfo.html
[2]: http://php.net/splfileinfo
[3]: https://symfony.com/doc/current/components/finder.html#usage
