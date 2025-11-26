<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Translation;

interface TranslationDomainAwareInterface
{
    /**
     * Sets the current translation domain.
     *
     * @return void
     */
    public function setDomain(string $domain);

    /**
     * Returns the current translation domain.
     */
    public function getDomain(): string;
}
