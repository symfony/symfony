<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * @author Bozhidar Hristov <warxcell@gmail.com>
 */
interface TranslatorFallbackInterface extends TranslatorInterface
{
    /**
     * @return string[] $locales
     */
    public function getFallbackLocales();
}
