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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Warning;
use PHPUnit\Util\Test;

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
    private $warningOnSutNotFound;
    private $warnings;

    public function __construct(callable $sutFqcnResolver = null, $warningOnSutNotFound = false)
    {
        $this->sutFqcnResolver = $sutFqcnResolver;
        $this->warningOnSutNotFound = $warningOnSutNotFound;
        $this->warnings = array();
    }

    public function startTest($test)
    {
        if (!$test instanceof TestCase) {
            return;
        }

        $annotations = $test->getAnnotations();

        $ignoredAnnotations = array('covers', 'coversDefaultClass', 'coversNothing');

        foreach ($ignoredAnnotations as $annotation) {
            if (isset($annotations['class'][$annotation]) || isset($annotations['method'][$annotation])) {
                return;
            }
        }

        $sutFqcn = $this->findSutFqcn($test);
        if (!$sutFqcn) {
            if ($this->warningOnSutNotFound) {
                $message = 'Could not find the tested class.';
                // addWarning does not exist on old PHPUnit version
                if (method_exists($test->getTestResultObject(), 'addWarning') && class_exists(Warning::class)) {
                    $test->getTestResultObject()->addWarning($test, new Warning($message), 0);
                } else {
                    $this->warnings[] = sprintf("%s::%s\n%s", \get_class($test), $test->getName(), $message);
                }
            }

            return;
        }

        $r = new \ReflectionProperty(Test::class, 'annotationCache');
        $r->setAccessible(true);

        $cache = $r->getValue();
        $cache = array_replace_recursive($cache, array(
            \get_class($test) => array(
                'covers' => \is_array($sutFqcn) ? $sutFqcn : array($sutFqcn),
            ),
        ));
        $r->setValue(Test::class, $cache);
    }

    private function findSutFqcn($test)
    {
        if ($this->sutFqcnResolver) {
            $resolver = $this->sutFqcnResolver;

            return $resolver($test);
        }

        $class = \get_class($test);

        $sutFqcn = str_replace('\\Tests\\', '\\', $class);
        $sutFqcn = preg_replace('{Test$}', '', $sutFqcn);

        return class_exists($sutFqcn) ? $sutFqcn : null;
    }

    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        if (!$this->warnings) {
            return;
        }

        echo "\n";

        foreach ($this->warnings as $key => $warning) {
            echo sprintf("%d) %s\n", ++$key, $warning);
        }
    }
}
