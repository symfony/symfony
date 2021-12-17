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
        case str_contains($file = realpath($file), '/vendor/'):
        case str_contains($file, '/src/Symfony/Bridge/PhpUnit/'):
        case str_contains($file, '/src/Symfony/Bundle/FrameworkBundle/Tests/Fixtures/Validation/Article.php'):
        case str_contains($file, '/src/Symfony/Component/Cache/Tests/Fixtures/DriverWrapper.php'):
        case str_contains($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadFileName.php'):
        case str_contains($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadParent.php'):
        case str_contains($file, '/src/Symfony/Component/Config/Tests/Fixtures/ParseError.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Dumper/PhpDumper.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Compiler/OptionalServiceClass.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/autowiring_classes.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/intersectiontype_classes.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/MultipleArgumentsOptionalScalarNotReallyOptional.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/CheckTypeDeclarationsPass/IntersectionConstructor.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/NewInInitializer.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/ParentNotExists.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Preload/'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Prototype/BadClasses/MissingParent.php'):
        case str_contains($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/php/'):
        case str_contains($file, '/src/Symfony/Component/ErrorHandler/Tests/Fixtures/'):
        case str_contains($file, '/src/Symfony/Component/Form/Tests/Fixtures/Answer.php'):
        case str_contains($file, '/src/Symfony/Component/Form/Tests/Fixtures/Number.php'):
        case str_contains($file, '/src/Symfony/Component/Form/Tests/Fixtures/Suit.php'):
        case str_contains($file, '/src/Symfony/Component/Messenger/Envelope.php'):
        case str_contains($file, '/src/Symfony/Component/PropertyInfo/Tests/Fixtures/'):
        case str_contains($file, '/src/Symfony/Component/PropertyInfo/Tests/Fixtures/Php81Dummy.php'):
        case str_contains($file, '/src/Symfony/Component/Runtime/Internal/ComposerPlugin.php'):
        case str_contains($file, '/src/Symfony/Component/Serializer/Tests/Fixtures/'):
        case str_contains($file, '/src/Symfony/Component/Serializer/Tests/Normalizer/Features/ObjectOuter.php'):
        case str_contains($file, '/src/Symfony/Component/Validator/Tests/Fixtures/NestedAttribute/Entity.php'):
        case str_contains($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/NotLoadableClass.php'):
        case str_contains($file, '/src/Symfony/Component/VarDumper/Tests/Fixtures/ReflectionIntersectionTypeFixture.php'):
            continue 2;
    }

    class_exists($class);
}

Symfony\Component\ErrorHandler\DebugClassLoader::checkClasses();
