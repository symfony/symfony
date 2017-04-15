<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Flag;

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Flag\Exception\InvalidArgumentException;

/**
 * Base class implementing the FlagInterface.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
abstract class AbstractFlag implements FlagInterface
{
    protected $from;
    protected $prefix;
    protected $bitfield;
    protected $flags = array();

    use LoggerAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function __construct($from = false, $prefix = '', $bitfield = 0)
    {
        $this->from = $from;
        $this->prefix = $prefix;
        $this->set($bitfield);

        if (false !== $this->from) {
            $this->flags = self::search($this->from, $this->prefix);
        }
    }

    /**
     * Creates dynamically instance of Flag.
     *
     * @param string|null|bool $from         Class from where the searching flags is made; define to null to
     *                                       search flags in global space; define to false for standalone use
     * @param string           $prefix       Prefix flags from the search flags is made
     * @param bool             $hierarchical Defines hierarchical flags
     * @param int              $bitfield     Sets bitfield value
     *
     * @return AbstractFlag
     *
     * @throws InvalidArgumentException When standalone use is defined as hierarchical
     * @throws InvalidArgumentException When no-integer flags is defined as hierarchical
     */
    public static function create($from = false, $prefix = '', $hierarchical = false, $bitfield = 0)
    {
        $onlyInt = true;
        $forceToBinarize = false;

        if (false === $from) {
            if ($hierarchical) {
                throw new InvalidArgumentException('Potential no-integer flags must not be hierarchical.');
            }
            $forceToBinarize = true;
        } else {
            foreach (self::search($from, $prefix) as $value => $flag) {
                if (!is_int($value)) {
                    $onlyInt = false;
                    break;
                }
            }
            if ($hierarchical && !$onlyInt) {
                throw new InvalidArgumentException('No-integer flags must not be hierarchical.');
            }
        }

        switch (true) {
            case !$forceToBinarize && $onlyInt && !$hierarchical:
                $class = Flag::class;
                break;
            case !$forceToBinarize && $onlyInt && $hierarchical:
                $class = HierarchicalFlag::class;
                break;
            default:
                $class = BinarizedFlag::class;
        }

        return new $class($from, $prefix, $bitfield);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        // ID can be "Prefix*", "ShortClass", "ShortClass::Prefix*" or empty in standalone case
        $id = '';
        if (null === $this->from) {
            $id = $this->prefix.'*';
        } elseif (class_exists($this->from)) {
            $id = (new \ReflectionClass($this->from))->getShortName();
            if ('' !== $this->prefix) {
                $id .= '::'.$this->prefix.'*';
            }
        }

        // removes prefix of each flag (example with E_ prefix, E_ALL becomes ALL)
        $flags = $this->prefix
            ? array_map(function ($flag) { return substr($flag, strlen($this->prefix)); }, iterator_to_array($this))
            : iterator_to_array($this);
        ;

        return trim(sprintf(
            '%s [bin: %b] [dec: %s] [%s: %s]',
            $id,
            $this->bitfield,
            $this->bitfield,
            $this->prefix ? $this->prefix.'*' : 'flags',
            implode(' | ', $flags)
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        return $this->bitfield;
    }

    /**
     * {@inheritdoc}
     */
    public function set($bitfield)
    {
        if ($this->bitfield !== $bitfield) {
            $this->bitfield = $bitfield;
            if (null !== $this->logger) {
                $this->logger->debug('bitfield changed {flag}', array('flag' => (string) $this));
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator($flagged = true)
    {
        return new \ArrayIterator($flagged
            ? array_filter($this->flags, function ($v) { return $this->has($v); }, ARRAY_FILTER_USE_KEY)
            : $this->flags
        );
    }

    /**
     * Searchs flags from class or global space.
     *
     * @param null|string $from   Class from where the flags searching flags is made; define to null to search flags
     *                            in global space
     * @param string      $prefix Prefix flags that filter search result
     *
     * @return array Array of flags.
     */
    public static function search($from, $prefix = '')
    {
        if (null === $from && '' === $prefix) {
            throw new InvalidArgumentException('A prefix must be setted if searching is in global space.');
        }

        // TODO search in namespaced constants (get_defined_constants(true)['user'])
        $constants = null === $from
            ? get_defined_constants()
            : (new \ReflectionClass($from))->getConstants()
        ;

        if ('' !== $prefix) {
            foreach ($constants as $constant => $value) {
                if (0 !== strpos($constant, $prefix)) {
                    unset($constants[$constant]);
                }
            }
        }

        return array_flip($constants);
    }
}
