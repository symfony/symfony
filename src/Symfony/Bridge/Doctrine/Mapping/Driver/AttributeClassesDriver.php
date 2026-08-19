<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Mapping\Driver;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;

/**
 * Reads attribute mapping from an explicit list of classes instead of scanning directories.
 *
 * @author Nayte91 <nayte91@gmail.com>
 */
final class AttributeClassesDriver extends AttributeDriver
{
    /**
     * @param class-string[] $classes
     */
    public function __construct(
        private readonly array $classes = [],
    ) {
        parent::__construct([]);
    }

    public function getAllClassNames(): array
    {
        return $this->classes;
    }
}
