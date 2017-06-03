<?php
namespace Symfony\Bundle\FrameworkBundle\Tests\ControllerMetadata;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Factory\ConfigurationAnnotationAdapterFactory;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Factory\ChainAnnotationAdapterFactory;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Factory\RouteAnnotationAdapterFactory;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\MethodMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @covers Symfony\Bundle\FrameworkBundle\Tests\ControllerMetadata\ClassMetadata
 */
class ClassMetadataTest extends KernelTestCase
{
    public function testOldFew()
    {
        echo "\nRunning current situation with a few controllers, annotations and reflection loaded each request\n";
        $times = 1000;
        $before = microtime(true);

        AnnotationRegistry::registerLoader('class_exists');
        $reader = new AnnotationReader();

        $controllers = [
            InvokableClassLevelController::class,
            InvokableContainerController::class,
            InvokableController::class,
            MultipleActionsClassLevelTemplateController::class,
            SimpleController::class,
        ];

        $data = [];
        $total = 0;

        for ($i = 0; $i < $times; $i++) {
            $annotations = [];
            foreach ($controllers as $controller) {
                $c = new \ReflectionClass($controller);
                $annotations[$controller] = $reader->getClassAnnotations($c);
                $total += count($annotations[$controller]);
                foreach ($c->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    $annotations[$method->getName()] = $reader->getMethodAnnotations($method);
                    $total += count($annotations[$method->getName()]);
                }
            }
            $data[] = $annotations;
        }

        $after = microtime(true);
        $time = round(($after - $before) * 1000);
        $pr = round($time / $times, 2);
        echo " * Executed $times times: $time ms\n   - 5 controllers\n   - $total annotations total\n   - $pr ms/request\n\n";
    }

    public function testOldMany()
    {
        echo "\nRunning current situation with many controllers, annotations and reflection loaded each request\n";
        $times = 10;
        $controllerCount = 100;
        $before = microtime(true);

        AnnotationRegistry::registerLoader('class_exists');
        $reader = new AnnotationReader();

        $controllers = [
            InvokableClassLevelController::class,
            InvokableContainerController::class,
            InvokableController::class,
            MultipleActionsClassLevelTemplateController::class,
            SimpleController::class,
        ];

        $total = 0;

        for ($j = 0; $j < $controllerCount; $j++) {
            $data = [];
            for ($i = 0; $i < $times; $i++) {
                $annotations = [];
                foreach ($controllers as $controller) {
                    $c = new \ReflectionClass($controller);
                    $annotations[$controller] = $reader->getClassAnnotations($c);
                    $total += count($annotations[$controller]);
                    foreach ($c->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        $annotations[$method->getName()] = $reader->getMethodAnnotations($method);
                        $total += count($annotations[$method->getName()]);
                    }
                }
                $data[] = $annotations;
            }
        }

        $after = microtime(true);
        $time = round(($after - $before) * 1000);
        $c = count($controllers) * $controllerCount;
        $pr = round($time / $times, 2);
        $sr = round($pr / $c, 2);
        echo " * Executed $times times: $time ms\n   - $c controllers\n   - $total annotations total\n   - $pr ms/request\n   - $sr ms/sub-request\n\n";
    }

    public function testBootstrapFewControllers()
    {
        echo "\nRunning new situation with a few controllers, annotations and reflection loaded during cache warmup\n";
        $before = microtime(true);

        $reader = new AnnotationReader();

        $f1 = new ConfigurationAnnotationAdapterFactory();
        $f2 = new RouteAnnotationAdapterFactory([$f1]);
        $f3 = new ChainAnnotationAdapterFactory([$f1, $f2]);

        $controllers = [
            InvokableClassLevelController::class,
            InvokableContainerController::class,
            InvokableController::class,
            MultipleActionsClassLevelTemplateController::class,
            SimpleController::class,
        ];

        $data = [];
        $total = 0;

        foreach ($controllers as $controller) {
            $classAnnotations = [];
            $c                = new \ReflectionClass($controller);
            foreach ($reader->getClassAnnotations($c) as $annotation) {
                $classAnnotations[] = $f3->createForAnnotation($annotation);
            }
            $total += count($classAnnotations);
            $methods = [];
            foreach ($c->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $methodAnnotations = [];
                foreach ($reader->getMethodAnnotations($method) as $annotation) {
                    $methodAnnotations[] = $f3->createForAnnotation($annotation);
                }
                $total += count($methodAnnotations);
                $methods[] = new MethodMetadata($method->getName(), $methodAnnotations);
            }
            $data[] = new ClassMetadata($c->getName(), $methods, $classAnnotations);
        }

        file_put_contents(__DIR__.'/dump_few.serialized', serialize($data));

        $after = microtime(true);
        $time = round(($after - $before) * 1000);
        echo " * bootstrap executed in $time ms\n   - 4 controllers\n   - $total annotations\n";
    }

    /**
     * @depends testBootstrapFewControllers
     */
    public function testNewFewControllers()
    {
        $times = 1000;
        $before = microtime(true);
        $data = [];

        for ($i = 0; $i < $times; $i++) {
            $data = unserialize(file_get_contents(__DIR__.'/dump_few.serialized'));
        }

        $after = microtime(true);
        $time = round(($after - $before) * 1000);
        $c = count($data);
        $pr = round($time / $times, 2);
        echo " * Executed $times times: $time ms\n   - $c controllers\n   - $pr ms/request\n";
    }


    public function testBootstrapManyControllers()
    {
        echo "\n\nRunning new situation with many controllers, annotations and reflection loaded during cache warmup\n";
        $before = microtime(true);

        $reader = new AnnotationReader();

        $f1 = new ConfigurationAnnotationAdapterFactory();
        $f2 = new RouteAnnotationAdapterFactory([$f1]);
        $f3 = new ChainAnnotationAdapterFactory([$f1, $f2]);

        $controllers = [
            InvokableClassLevelController::class,
            InvokableContainerController::class,
            InvokableController::class,
            MultipleActionsClassLevelTemplateController::class,
            SimpleController::class,
        ];

        $data = [];
        $total = 0;

        for ($i = 0; $i < 100; $i++)
        foreach ($controllers as $controller) {
            $classAnnotations = [];
            $c                = new \ReflectionClass($controller);
            foreach ($reader->getClassAnnotations($c) as $annotation) {
                $classAnnotations[] = $f3->createForAnnotation($annotation);
            }
            $total += count($classAnnotations);
            $methods = [];
            foreach ($c->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $methodAnnotations = [];
                foreach ($reader->getMethodAnnotations($method) as $annotation) {
                    $methodAnnotations[] = $f3->createForAnnotation($annotation);
                }
                $total += count($methodAnnotations);
                $methods[] = new MethodMetadata($method->getName(), $methodAnnotations);
            }
            $data[] = new ClassMetadata($c->getName(), $methods, $classAnnotations);
        }


        file_put_contents(__DIR__.'/dump_many.serialized', serialize($data));

        $after = microtime(true);
        $time = round(($after - $before) * 1000);
        $c = $i * 5;
        echo " * new bootstrap executed in $time ms\n   - $c controllers\n   - $total annotations\n";
    }

    /**
     * @depends testBootstrapManyControllers
     */
    public function testNewManyControllers()
    {
        $times = 1000;
        $before = microtime(true);

        $data = [];
        for ($i = 0; $i < $times; $i++) {
            $data = unserialize(file_get_contents(__DIR__.'/dump_many.serialized'));
        }

        $after = microtime(true);
        $time = round(($after - $before) * 1000);
        $c = count($data);
        $pr = round($time / $times, 2);
        echo " * New Executed $times times: $time ms\n   - $c controllers\n   - $pr ms/request\n";
    }
}
