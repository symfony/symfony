<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
abstract class AbstractQuery implements QueryInterface
{
    protected $connection;
    protected $dn;
    protected $query;
    protected $options;

    public function __construct(ConnectionInterface $connection, string $dn, string $query, array $options = array())
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'filter' => '*',
            'maxItems' => 0,
            'sizeLimit' => 0,
            'timeout' => 0,
            'deref' => QueryInterface::DEREF_NEVER,
            'attrsOnly' => 0,
            'scope' => QueryInterface::SCOPE_SUB,
        ));
        $resolver->setAllowedValues('deref', array(QueryInterface::DEREF_ALWAYS, QueryInterface::DEREF_NEVER, QueryInterface::DEREF_FINDING, QueryInterface::DEREF_SEARCHING));
        $resolver->setAllowedValues('scope', array(QueryInterface::SCOPE_BASE, QueryInterface::SCOPE_ONE, QueryInterface::SCOPE_SUB));

        $resolver->setNormalizer('filter', function (Options $options, $value) {
            return \is_array($value) ? $value : array($value);
        });

        $this->connection = $connection;
        $this->dn = $dn;
        $this->query = $query;
        $this->options = $resolver->resolve($options);
    }
}
