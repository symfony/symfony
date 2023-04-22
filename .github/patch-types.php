<?php

$mode = $argv[1] ?? 'patch';
if ('lint' !== $mode && false === getenv('SYMFONY_PATCH_TYPE_DECLARATIONS')) {
    echo "Please define the SYMFONY_PATCH_TYPE_DECLARATIONS env var when running this script.\n";
    exit(1);
}

require __DIR__.'/../.phpunit/phpunit/vendor/autoload.php';

$loader = require __DIR__.'/../vendor/autoload.php';

Symfony\Component\ErrorHandler\DebugClassLoader::enable();

$missingReturnTypes = [];
foreach ($loader->getClassMap() as $class => $file) {
    $file = realpath($file);

    switch (true) {
        case false !== strpos($file, '/src/Symfony/Component/Cache/Traits/Redis'):
            if (!str_ends_with($file, 'Proxy.php')) {
                break;
            }
            // no break;
        case false !== strpos($file, '/vendor/'):
        case false !== strpos($file, '/src/Symfony/Bridge/PhpUnit/'):
        case false !== strpos($file, '/src/Symfony/Bundle/FrameworkBundle/Tests/Fixtures/Validation/Article.php'):
        case false !== strpos($file, '/src/Symfony/Component/Cache/Tests/Fixtures/DriverWrapper.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadFileName.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/BadParent.php'):
        case false !== strpos($file, '/src/Symfony/Component/Config/Tests/Fixtures/ParseError.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Dumper/PhpDumper.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Compiler/OptionalServiceClass.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/autowiring_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/compositetype_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/intersectiontype_classes.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/includes/MultipleArgumentsOptionalScalarNotReallyOptional.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/CheckTypeDeclarationsPass/IntersectionConstructor.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/NewInInitializer.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/ParentNotExists.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Preload/'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/Prototype/BadClasses/MissingParent.php'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/php/'):
        case false !== strpos($file, '/src/Symfony/Component/DependencyInjection/Tests/Fixtures/TestServiceSubscriberIntersectionWithTrait.php'):
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
        case false !== strpos($file, '/src/Symfony/Component/VarExporter/Internal'):
        case false !== strpos($file, '/src/Symfony/Component/VarExporter/Tests/Fixtures/LazyGhost/ReadOnlyClass.php'):
        case false !== strpos($file, '/src/Symfony/Component/VarExporter/Tests/Fixtures/LazyProxy/ReadOnlyClass.php'):
        case false !== strpos($file, '/src/Symfony/Component/Cache/Traits/RelayProxy.php'):
        case false !== strpos($file, '/src/Symfony/Contracts/Service/Test/ServiceLocatorTest.php'):
        case false !== strpos($file, '/src/Symfony/Contracts/Service/Test/ServiceLocatorTestCase.php'):
            continue 2;
    }

    class_exists($class);

    if ('lint' !== $mode) {
        continue;
    }

    $refl = new \ReflectionClass($class);
    foreach ($refl->getMethods() as $method) {
        if (
            !$refl->isInterface()
            || $method->getReturnType()
            || str_contains($method->getDocComment(), '@return')
            || str_starts_with($method->getName(), '__')
            || $method->getDeclaringClass()->getName() !== $class
        ) {
            continue;
        }

        $missingReturnTypes[] = $class.'::'.$method->getName();
    }
}

if ($missingReturnTypes) {
    echo \count($missingReturnTypes)." missing return types on interfaces\n\n";
    echo implode("\n", $missingReturnTypes);
    exit(1);
}

if ('patch' === $mode) {
    Symfony\Component\ErrorHandler\DebugClassLoader::checkClasses();
}
