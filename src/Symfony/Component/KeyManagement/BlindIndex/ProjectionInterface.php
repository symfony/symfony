<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\BlindIndex;

/**
 * What of a value a blind index actually indexes, applied on the way in and on the way out alike.
 *
 * It is a class rather than something a call site does first: an index computed on a trimmed value
 * and searched on an untrimmed one silently matches nothing, which is the failure a blind index
 * exists to avoid. The component ships {@see Projection\Verbatim}, {@see Projection\Email} and
 * {@see Projection\EmailDomain}; anything more specific to a domain, a national identifier or an
 * account number, is a handful of lines in an application.
 *
 * It is also how the `BlindIndexed` attribute of `symfony/doctrine-orm-key-management` names an
 * index, so two indexes of one application have two projections.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface ProjectionInterface
{
    public function project(#[\SensitiveParameter] string $value): string;
}
