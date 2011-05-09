<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GenerateLookupMethodClassesPass implements CompilerPassInterface
{
    private $generatedClasses = array();

    public function process(ContainerBuilder $container)
    {
        $this->generatedClasses = array();
        $this->cleanUpCacheDir($cacheDir = $container->getParameter('kernel.cache_dir').'/lookup_method_classes');

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }
            if (!$methods = $definition->getLookupMethods()) {
                continue;
            }

            $this->generateClass($definition, $cacheDir);
        }
    }

    private function cleanUpCacheDir($dir)
    {
        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true)) {
                throw new \RuntimeException(sprintf('The cache directory "%s" could not be created.', $dir));
            }

            return;
        }

        if (!is_writable($dir)) {
            throw new \RuntimeException(sprintf('The cache directory "%s" is not writable.', $dir));
        }

        foreach (new \DirectoryIterator($dir) as $file) {
            if ('.' === $file->getFileName() || !is_file($file->getPathName())) {
                continue;
            }

            if (false === @unlink($file->getPathName())) {
                throw new \RuntimeException(sprintf('Could not delete auto-generated file "%s".', $file->getPathName()));
            }
        }
    }

    private function generateClass(Definition $definition, $cacheDir)
    {
        $code = <<<'EOF'
<?php

namespace Symfony\Component\DependencyInjection\LookupMethodClasses;
%s
class %s extends %s
{
    private $__symfonyDependencyInjectionContainer;
%s
}
EOF;

        // other file requirement
        if ($file = $definition->getFile()) {
            $require = sprintf("\nrequire_once %s;\n", var_export($file, true));
        } else {
            $require = '';
        }

        // get class name
        $class = new \ReflectionClass($definition->getClass());
        $i = 1;
        do {
            $className = $class->getShortName();

            if ($i > 1) {
                $className .= '_'.$i;
            }

            $i += 1;
        } while (isset($this->generatedClasses[$className]));
        $this->generatedClasses[$className] = true;

        $lookupMethod = <<<'EOF'

    %s function %s()
    {
        return %s;
    }
EOF;
        $lookupMethods = '';
        foreach ($definition->getLookupMethods() as $name => $value) {
            if (!$class->hasMethod($name)) {
                throw new \RuntimeException(sprintf('The class "%s" has no method named "%s".', $class->getName(), $name));
            }
            $method = $class->getMethod($name);
            if ($method->isFinal()) {
                throw new \RuntimeException(sprintf('The method "%s::%s" is marked as final and cannot be declared as lookup-method.', $class->getName(), $name));
            }
            if ($method->isPrivate()) {
                throw new \RuntimeException(sprintf('The method "%s::%s" is marked as private and cannot be declared as lookup-method.', $class->getName(), $name));
            }
            if ($method->getParameters()) {
                throw new \RuntimeException(sprintf('The method "%s::%s" must have a no-arguments signature if you want to use it as lookup-method.', $class->getName(), $name));
            }

            $lookupMethods .= sprintf($lookupMethod,
                $method->isPublic() ? 'public' : 'protected',
                $name,
                'foo' // FIXME: refactor PhpDumper::dumpValue code
            );
        }

        $code = sprintf($code, $require, $className, $class->getName(), $lookupMethods);
        file_put_contents($cacheDir.'/'.$className.'.php', $code);
        $definition->setFile($cacheDir.'/'.$className.'.php');
        $definition->setProperty('__symfonyDependencyInjectionContainer', new Reference('service_container'));
    }
}