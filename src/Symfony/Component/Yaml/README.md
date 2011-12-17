Yaml Component
==============

YAML is a great configuration format. It's the most popular Symfony2 component
right now, because this is probably the only plain-PHP library that implements
most of the YAML 1.2 specification:

```
use Symfony\Component\Yaml\Yaml;

$array = Yaml::parse($file);

print Yaml::dump($array);
```

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/Yaml
