<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\Warning;
use PHPUnit\Util\Annotation\Registry;
use PHPUnit\Util\Test as TestUtil;

class CoverageListener implements TestListener
{
    use TestListenerDefaultImplementation;

    private $sutFqcnResolver;
    private $warningOnSutNotFound;

    public function __construct(callable $sutFqcnResolver = null, bool $warningOnSutNotFound = false)
    {
        $this->sutFqcnResolver = $sutFqcnResolver ?? static function (Test $test): ?string {
            $class = \get_class($test);

            $sutFqcn = str_replace('\\Tests\\', '\\', $class);
            $sutFqcn = preg_replace('{Test$}', '', $sutFqcn);

            return class_exists($sutFqcn) ? $sutFqcn : null;
        };

        $this->warningOnSutNotFound = $warningOnSutNotFound;
    }

    public function startTest(Test $test): void
    {
        if (!$test instanceof TestCase) {
            return;
        }

        $annotations = TestUtil::parseTestMethodAnnotations(\get_class($test), $test->getName(false));

        $ignoredAnnotations = ['covers', 'coversDefaultClass', 'coversNothing'];

        foreach ($ignoredAnnotations as $annotation) {
            if (isset($annotations['class'][$annotation]) || isset($annotations['method'][$annotation])) {
                return;
            }
        }

        $sutFqcn = ($this->sutFqcnResolver)($test);
        if (!$sutFqcn) {
            if ($this->warningOnSutNotFound) {
                $test->getTestResultObject()->addWarning($test, new Warning('Could not find the tested class.'), 0);
            }

            return;
        }

        $covers = $sutFqcn;
        if (!\is_array($sutFqcn)) {
            $covers = [$sutFqcn];
            while ($parent = get_parent_class($sutFqcn)) {
                $covers[] = $parent;
                $sutFqcn = $parent;
            }
        }

        if (class_exists(Registry::class)) {
            $this->addCoversForDocBlockInsideRegistry($test, $covers);

            return;
        }

        $this->addCoversForClassToAnnotationCache($test, $covers);
    }

    private function addCoversForClassToAnnotationCache(Test $test, array $covers): void
    {
        $r = new \ReflectionProperty(TestUtil::class, 'annotationCache');
        $r->setAccessible(true);

        $cache = $r->getValue();
        $cache = array_replace_recursive($cache, [
            \get_class($test) => [
                'covers' => $covers,
            ],
        ]);

        $r->setValue(TestUtil::class, $cache);
    }

    private function addCoversForDocBlockInsideRegistry(Test $test, array $covers): void
    {
        $docBlock = Registry::getInstance()->forClassName(\get_class($test));

        $symbolAnnotations = new \ReflectionProperty($docBlock, 'symbolAnnotations');
        $symbolAnnotations->setAccessible(true);

        // Exclude internal classes; PHPUnit 9.1+ is picky about tests covering, say, a \RuntimeException
        $covers = array_filter($covers, function (string $class) {
            $reflector = new \ReflectionClass($class);

            return $reflector->isUserDefined();
        });

        $symbolAnnotations->setValue($docBlock, array_replace($docBlock->symbolAnnotations(), [
            'covers' => $covers,
        ]));
    }
}
