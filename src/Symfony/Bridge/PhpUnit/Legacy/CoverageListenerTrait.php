<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use PHPUnit\Framework\Test;

/**
 * PHP 5.3 compatible trait-like shared implementation.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
class CoverageListenerTrait
{
    private $sutFqcnResolver;

    public function __construct(callable $sutFqcnResolver = null)
    {
        $this->sutFqcnResolver = $sutFqcnResolver;
    }

    public function startTest($test)
    {
        $annotations = $test->getAnnotations();

        if (isset($annotations['class']['covers']) || isset($annotations['method']['covers'])) {
            return;
        }

        $sutFqcn = $this->findSutFqcn($test);
        if (!$sutFqcn) {
            return;
        }

        $testClass = \PHPUnit\Util\Test::class;
        if (!class_exists($testClass, false)) {
            $testClass = \PHPUnit_Util_Test::class;
        }

        $r = new \ReflectionProperty($testClass, 'annotationCache');
        $r->setAccessible(true);

        $cache = $r->getValue();
        $cache = array_replace_recursive($cache, array(
            get_class($test) => array(
                'covers' => array($sutFqcn),
            ),
        ));
        $r->setValue($testClass, $cache);
    }

    private function findSutFqcn($test)
    {
        if ($this->sutFqcnResolver) {
            $resolver = $this->sutFqcnResolver;

            return $resolver($test);
        }

        $class = get_class($test);

        $sutFqcn = str_replace('Tests\\', '', $class);
        $sutFqcn = preg_replace('{Test$}', '', $sutFqcn);

        if (!class_exists($sutFqcn)) {
            return;
        }

        return $sutFqcn;
    }
}
