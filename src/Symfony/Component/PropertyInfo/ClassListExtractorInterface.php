<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Extract a list of class for a specific domain.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface ClassListExtractorInterface
{
    /**
     * @return iterable An array of FQDN classes
     */
    public function getClasses(array $context = []): iterable;
}
