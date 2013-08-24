<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Catalogue;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * Base catalogues binary operation class.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @since v2.3.0
 */
abstract class AbstractOperation implements OperationInterface
{
    /**
     * @var MessageCatalogueInterface
     */
    protected $source;

    /**
     * @var MessageCatalogueInterface
     */
    protected $target;

    /**
     * @var MessageCatalogue
     */
    protected $result;

    /**
     * @var null|array
     */
    private $domains;

    /**
     * @var array
     */
    protected $messages;

    /**
     * @param MessageCatalogueInterface $source
     * @param MessageCatalogueInterface $target
     *
     * @throws \LogicException
     *
     * @since v2.3.0
     */
    public function __construct(MessageCatalogueInterface $source, MessageCatalogueInterface $target)
    {
        if ($source->getLocale() !== $target->getLocale()) {
            throw new \LogicException('Operated catalogues must belong to the same locale.');
        }

        $this->source = $source;
        $this->target = $target;
        $this->result = new MessageCatalogue($source->getLocale());
        $this->domains = null;
        $this->messages = array();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getDomains()
    {
        if (null === $this->domains) {
            $this->domains = array_values(array_unique(array_merge($this->source->getDomains(), $this->target->getDomains())));
        }

        return $this->domains;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getMessages($domain)
    {
        if (!in_array($domain, $this->getDomains())) {
            throw new \InvalidArgumentException(sprintf('Invalid domain: %s.', $domain));
        }

        if (!isset($this->messages[$domain]['all'])) {
            $this->processDomain($domain);
        }

        return $this->messages[$domain]['all'];
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getNewMessages($domain)
    {
        if (!in_array($domain, $this->getDomains())) {
            throw new \InvalidArgumentException(sprintf('Invalid domain: %s.', $domain));
        }

        if (!isset($this->messages[$domain]['new'])) {
            $this->processDomain($domain);
        }

        return $this->messages[$domain]['new'];
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getObsoleteMessages($domain)
    {
        if (!in_array($domain, $this->getDomains())) {
            throw new \InvalidArgumentException(sprintf('Invalid domain: %s.', $domain));
        }

        if (!isset($this->messages[$domain]['obsolete'])) {
            $this->processDomain($domain);
        }

        return $this->messages[$domain]['obsolete'];
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getResult()
    {
        foreach ($this->getDomains() as $domain) {
            if (!isset($this->messages[$domain])) {
                $this->processDomain($domain);
            }
        }

        return $this->result;
    }

    /**
     * @param string $domain
     *
     * @since v2.3.0
     */
    abstract protected function processDomain($domain);
}
