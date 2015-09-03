<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

/**
 * Base interface to test arbitrary metadata for freshness.
 *
 * @author Benjamin Klotz <bk@webfactory.de>
 */
interface MetadataValidatorInterface
{

    public function supports($metadata);

    public function isFresh($metadata, $timestamp);

}