<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Extractor;

use Symfony\Component\PropertyInfo\ClassListExtractorInterface;

/**
 * Extracts class from a specific collection.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ClassCollectionExtractor implements ClassListExtractorInterface
{
    private $classes;

    /**
     * @param string[] $classes
     */
    public function __construct(array $classes = [])
    {
        $this->classes = $classes;
    }

    /**
     * {@inheritdoc}
     */
    public function getClasses(array $context = []): iterable
    {
        return $this->classes;
    }
}
