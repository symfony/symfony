<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

/**
 * @internal
 */
final class DeprecationNotice
{
    private $count = 0;

    /**
     * @var int[]
     */
    private $countsByCaller = [];

    public function addObjectOccurrence($class, $method)
    {
        if (!isset($this->countsByCaller["$class::$method"])) {
            $this->countsByCaller["$class::$method"] = 0;
        }
        ++$this->countsByCaller["$class::$method"];
        ++$this->count;
    }

    public function addProceduralOccurrence()
    {
        ++$this->count;
    }

    public function getCountsByCaller()
    {
        return $this->countsByCaller;
    }

    public function count()
    {
        return $this->count;
    }
}
