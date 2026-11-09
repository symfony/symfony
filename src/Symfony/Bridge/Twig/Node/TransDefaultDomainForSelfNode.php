<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Node\ConfigNode;

/**
 * Represents a "trans_default_domain ... for _self" tag, which Twig requires at the root of the template.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
#[YieldReady]
final class TransDefaultDomainForSelfNode extends ConfigNode
{
    public function __construct(string $domain, int $lineno)
    {
        parent::__construct($lineno);

        $this->setAttribute('domain', $domain);
    }
}
