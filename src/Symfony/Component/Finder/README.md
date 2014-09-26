Finder Component
================

Finder finds files and directories via an intuitive fluent interface.

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

The resulting contents of the iterator are instances of [SplFileInfo][1]. You can 
thus use all of SplFileInfo's methods (getPerms(), getSize(), etc) on them. See
[the API documentation][2] or the [web tutorial][3] for more.

But you can also use it to find files stored remotely like in this example where
we are looking for files on Amazon S3:

    $s3 = new \Zend_Service_Amazon_S3($key, $secret);
    $s3->registerStreamWrapper("s3");

    $finder = new Finder();
    $finder->name('photos*')->size('< 100K')->date('since 1 hour ago');
    foreach ($finder->in('s3://bucket-name') as $file) {
        print $file->getFilename()."\n";
    }

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Finder/
    $ composer.phar install
    $ phpunit

[1]: http://php.net/splfileinfo
[2]: http://api.symfony.com/2.5/Symfony/Component/Finder/SplFileInfo.html
[3]: http://symfony.com/doc/current/components/finder.html#usage
