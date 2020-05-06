<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter\ExtLdap;

use Symfony\Component\Ldap\Exception\UpdateOperationException;

class UpdateOperation
{
    private $operationType;
    private $values;
    private $attribute;

    private $validOperationTypes = [
        LDAP_MODIFY_BATCH_ADD,
        LDAP_MODIFY_BATCH_REMOVE,
        LDAP_MODIFY_BATCH_REMOVE_ALL,
        LDAP_MODIFY_BATCH_REPLACE,
    ];

    /**
     * @param int    $operationType An LDAP_MODIFY_BATCH_* constant
     * @param string $attribute     The attribute to batch modify on
     *
     * @throws UpdateOperationException on consistency errors during construction
     */
    public function __construct(int $operationType, string $attribute, ?array $values)
    {
        if (!\in_array($operationType, $this->validOperationTypes, true)) {
            throw new UpdateOperationException(sprintf('"%s" is not a valid modification type.', $operationType));
        }
        if (LDAP_MODIFY_BATCH_REMOVE_ALL === $operationType && null !== $values) {
            throw new UpdateOperationException(sprintf('$values must be null for LDAP_MODIFY_BATCH_REMOVE_ALL operation, "%s" given.', get_debug_type($values)));
        }

        $this->operationType = $operationType;
        $this->attribute = $attribute;
        $this->values = $values;
    }

    public function toArray(): array
    {
        return [
            'attrib' => $this->attribute,
            'modtype' => $this->operationType,
            'values' => $this->values,
        ];
    }
}
