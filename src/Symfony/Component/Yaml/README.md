Yaml Component
==============

YAML implements most of the YAML 1.2 specification.

    use Symfony\Component\Yaml\Yaml;

    $array = Yaml::parse($file);

    print Yaml::dump($array);

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Yaml/
    $ composer.phar install
    $ phpunit
