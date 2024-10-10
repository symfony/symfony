<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\NativeCrawler\Field;

use PHPUnit\Framework\TestCase;

/**
 * @requires PHP 8.4
 */
class FormFieldTestCase extends TestCase
{
    protected function createNode($tag, $attributes = [], ?string $value = null)
    {
        $node = \DOM\HTMLDocument::createEmpty()->createElement($tag);

        if (null !== $value) {
            $node->textContent = $value;
        }

        foreach ($attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }

        return $node;
    }
}
