<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Descriptor\JsonDescriptor;

class JsonDescriptorTest extends AbstractDescriptorTestCase
{
    protected function getDescriptor()
    {
        return new JsonDescriptor();
    }

    protected static function getFormat()
    {
        return 'json';
    }

    protected function normalizeOutput($output)
    {
        return array_map($this->normalizeOutputRecursively(...), json_decode($output, true));
    }

    private function normalizeOutputRecursively($output)
    {
        if (\is_array($output)) {
            return array_map($this->normalizeOutputRecursively(...), $output);
        }

        if (null === $output) {
            return null;
        }

        return parent::normalizeOutput($output);
    }
}
