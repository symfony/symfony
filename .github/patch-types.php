<?php

if (false === getenv('SYMFONY_PATCH_TYPE_DECLARATIONS')) {
    echo "Please define the SYMFONY_PATCH_TYPE_DECLARATIONS env var when running this script.\n";
    exit(1);
}

require __DIR__.'/../.phpunit/phpunit/vendor/autoload.php';

$loader = require __DIR__.'/../vendor/autoload.php';

Symfony\Component\ErrorHandler\DebugClassLoader::enable();

foreach ($loader->getClassMap() as $class => $file) {
    switch (true) {
        case false !== strpos(realpath($file), '/vendor/'):
        case false !== strpos($file, '/src/Symfony/Bridge/PhpUnit/'):
        case false !== strpos($file, '/src/Symfony/Bundle/FrameworkBundle/Tests/Fixtures/Validation/Article.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadFileName.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadParent.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/ParseError.php'):
        case false !== strpos($file, '/src/Symfony/Component/Debug/Tests/Fixtures/'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Compiler/OptionalServiceClass.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/CheckTypeDeclarationsPass/UnionConstructor.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/autowiring_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/autowiring_classes_80.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/uniontype_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/ParentNotExists.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Preload/'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Prototype/BadClasses/MissingParent.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/WitherStaticReturnType.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/php/'):
        case false !== strpos($file, '/src/Symfony/Component/ErrorHandler/Tests/Fixtures/'):
        case false !== strpos($file, '/src/Symfony/Component/HttpKernel/Tests/Fixtures/Controller/AttributeController.php'):
        case false !== strpos($file, '/src/Symfony/Component/PropertyInfo/Tests/Fixtures/Dummy.php'):
        case false !== strpos($file, '/src/Symfony/Component/PropertyInfo/Tests/Fixtures/ParentDummy.php'):
        case false !== strpos($file, '/src/Symfony/Component/PropertyInfo/Tests/Fixtures/Php80Dummy.php'):
        case false !== strpos($file, '/src/Symfony/Component/Routing/Tests/Fixtures/AttributeFixtures'):
        case false !== strpos($file, '/src/Symfony/Component/Serializer/Tests/Normalizer/Features/ObjectOuter.php'):
        case false !== strpos($file, '/src/Symfony/Component/Serializer/Tests/Fixtures/Attributes/'):
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/LotsOfAttributes.php'):
        case false !== strpos($file, '/src/Symfony/Component/Validator/Tests/Fixtures/Attribute/'):
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/MyAttribute.php'):
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/NotLoadableClass.php'):
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/Php74.php') && \PHP_VERSION_ID < 70400:
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/RepeatableAttribute.php'):
            continue 2;
    }

    class_exists($class);
}

Symfony\Component\ErrorHandler\DebugClassLoader::checkClasses();
