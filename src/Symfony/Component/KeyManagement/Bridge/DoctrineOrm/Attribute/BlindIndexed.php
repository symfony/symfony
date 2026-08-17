<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute;

use Symfony\Component\KeyManagement\BlindIndex;

/**
 * Declares that a property holds the blind index of another, and has it filled on flush.
 *
 * An encrypted column cannot be searched, so an application keeps a keyed tag of the value in a
 * sibling column and looks the row up by that. Writing the tag is mechanical and easy to forget,
 * and a row whose tag was not written is a row no search will ever return:
 *
 *     $patient->setEmail($email);
 *     $patient->setEmailIndex($index->of($email));   // this line, on every write path
 *
 * The attribute says once, on the column that holds the tag, where the tag comes from:
 *
 *     #[ORM\Column(type: 'encrypted_string')]
 *     private string $email = '';
 *
 *     #[ORM\Column(length: 64)]
 *     #[BlindIndexed('email', Email::class)]
 *     private string $emailIndex = '';
 *
 *     #[ORM\Column(length: 64)]
 *     #[BlindIndexed('email', EmailDomain::class)]
 *     private string $emailDomainIndex = '';
 *
 * It goes on the derived property rather than on the source, so that the column carries its own
 * derivation and two indexes cannot end up writing the same one.
 *
 * Four things it does not do, each of which leaves a tag that does not match its value.
 *
 * It covers the write path only. A query has no entity to hang the attribute on, so it keeps
 * calling `of()`, and the projection it names has to be the one the attribute names.
 *
 * It covers the ORM only. A row inserted through DBAL, or a bulk `UPDATE ... SET email = ...`,
 * never reaches a listener and leaves the tag as it was. That is worse than an empty tag: the
 * search then returns the row that used to hold the value.
 *
 * It only sees a property it can read. A value the entity computes on the way out, or holds in
 * anything other than a string, has to be projected by the application into a string property of
 * its own.
 *
 * And it cannot be done by a DBAL type, which is why it is a listener: a type converts one
 * property into one column, and this writes a second one.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BlindIndexed
{
    /**
     * @param string                   $property Name of the property this one indexes, declared on the same entity
     * @param class-string<BlindIndex> $index    Class of the blind index deriving the tag, as registered in the container
     */
    public function __construct(
        public string $property,
        public string $index,
    ) {
    }
}
