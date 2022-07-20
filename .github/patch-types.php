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
        case false !== strpos($file = realpath($file), '/vendor/'):
        case false !== strpos($file, '/src/Symfony/Bridge/PhpUnit/'):
        case false !== strpos($file, '/src/Symfony/Bundle/FrameworkBundle/Tests/Fixtures/Validation/Article.php'):
        case false !== strpos($file, '/src/Symfony/Component/Cache/Tests/Fixtures/DriverWrapper.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadFileName.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadParent.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/ParseError.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Compiler/OptionalServiceClass.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/autowiring_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/compositetype_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/intersectiontype_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/MultipleArgumentsOptionalScalarNotReallyOptional.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/CheckTypeDeclarationsPass/IntersectionConstructor.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/ParentNotExists.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Preload/'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Prototype/BadClasses/MissingParent.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/php/'):
        case false !== strpos($file, '/src/Symfony/Component/ErrorHandler/Tests/Fixtures/'):
        case false !== strpos($file, '/src/Symfony/Component/Form/Tests/Fixtures/Answer.php'):
        case false !== strpos($file, '/src/Symfony/Component/Form/Tests/Fixtures/Number.php'):
        case false !== strpos($file, '/src/Symfony/Component/Form/Tests/Fixtures/Suit.php'):
        case false !== strpos($file, '/src/Symfony/Component/PropertyInfo/Tests/Fixtures/'):
        case false !== strpos($file, '/src/Symfony/Component/PropertyInfo/Tests/Fixtures/Php81Dummy.php'):
        case false !== strpos($file, '/src/Symfony/Component/Runtime/Internal/ComposerPlugin.php'):
        case false !== strpos($file, '/src/Symfony/Component/Serializer/Tests/Fixtures/'):
        case false !== strpos($file, '/src/Symfony/Component/Serializer/Tests/Normalizer/Features/ObjectOuter.php'):
        case false !== strpos($file, '/src/Symfony/Component/Validator/Tests/Fixtures/NestedAttribute/Entity.php'):
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/NotLoadableClass.php'):
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/ReflectionIntersectionTypeFixture.php'):
        case false !== strpos($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/ReflectionUnionTypeWithIntersectionFixture.php'):
            continue 2;
    }

    class_exists($class);
}

Symfony\Component\ErrorHandler\DebugClassLoader::checkClasses();
