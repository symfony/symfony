<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Permission;

/**
 * This class allows you to build cumulative permissions easily, or convert
 * masks to a human-readable format.
 *
 * <code>
 *       $builder = new MaskBuilder();
 *       $builder
 *           ->add('view')
 *           ->add('create')
 *           ->add('edit')
 *       ;
 *       var_dump($builder->get());        // int(7)
 *       var_dump($builder->getPattern()); // string(32) ".............................ECV"
 * </code>
 *
 * We have defined some commonly used base permissions which you can use:
 * - VIEW: the SID is allowed to view the domain object / field
 * - CREATE: the SID is allowed to create new instances of the domain object / fields
 * - EDIT: the SID is allowed to edit existing instances of the domain object / field
 * - DELETE: the SID is allowed to delete domain objects
 * - UNDELETE: the SID is allowed to recover domain objects from trash
 * - OPERATOR: the SID is allowed to perform any action on the domain object
 *             except for granting others permissions
 * - MASTER: the SID is allowed to perform any action on the domain object,
 *           and is allowed to grant other SIDs any permission except for
 *           MASTER and OWNER permissions
 * - OWNER: the SID is owning the domain object in question and can perform any
 *          action on the domain object as well as grant any permission
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class MaskBuilder extends AbstractMaskBuilder
{
    const MASK_VIEW = 1;           // 1 << 0
    const MASK_CREATE = 2;         // 1 << 1
    const MASK_EDIT = 4;           // 1 << 2
    const MASK_DELETE = 8;         // 1 << 3
    const MASK_UNDELETE = 16;      // 1 << 4
    const MASK_OPERATOR = 32;      // 1 << 5
    const MASK_MASTER = 64;        // 1 << 6
    const MASK_OWNER = 128;        // 1 << 7
    const MASK_IDDQD = 1073741823; // 1 << 0 | 1 << 1 | ... | 1 << 30

    const CODE_VIEW = 'V';
    const CODE_CREATE = 'C';
    const CODE_EDIT = 'E';
    const CODE_DELETE = 'D';
    const CODE_UNDELETE = 'U';
    const CODE_OPERATOR = 'O';
    const CODE_MASTER = 'M';
    const CODE_OWNER = 'N';

    const ALL_OFF = '................................';
    const OFF = '.';
    const ON = '*';

    /**
     * Returns a human-readable representation of the permission.
     *
     * @return string
     */
    public function getPattern()
    {
        $pattern = self::ALL_OFF;
        $length = strlen($pattern);
        $bitmask = str_pad(decbin($this->mask), $length, '0', STR_PAD_LEFT);

        for ($i = $length - 1; $i >= 0; --$i) {
            if ('1' === $bitmask[$i]) {
                try {
                    $pattern[$i] = self::getCode(1 << ($length - $i - 1));
                } catch (\Exception $e) {
                    $pattern[$i] = self::ON;
                }
            }
        }

        return $pattern;
    }

    /**
     * Returns the code for the passed mask.
     *
     * @param int $mask
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return string
     */
    public static function getCode($mask)
    {
        if (!is_int($mask)) {
            throw new \InvalidArgumentException('$mask must be an integer.');
        }

        $reflection = new \ReflectionClass(get_called_class());
        foreach ($reflection->getConstants() as $name => $cMask) {
            if (0 !== strpos($name, 'MASK_') || $mask !== $cMask) {
                continue;
            }

            if (!defined($cName = 'static::CODE_'.substr($name, 5))) {
                throw new \RuntimeException('There was no code defined for this mask.');
            }

            return constant($cName);
        }

        throw new \InvalidArgumentException(sprintf('The mask "%d" is not supported.', $mask));
    }

    /**
     * Returns the mask for the passed code
     *
     * @param mixed $code
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function resolveMask($code)
    {
        if (is_string($code)) {
            if (!defined($name = sprintf('static::MASK_%s', strtoupper($code)))) {
                throw new \InvalidArgumentException(sprintf('The code "%s" is not supported', $code));
            }

            return constant($name);
        }

        if (!is_int($code)) {
            throw new \InvalidArgumentException('$code must be an integer.');
        }

        return $code;
    }
}
