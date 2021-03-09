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

use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * Base catalogues binary operation class.
 *
 * A catalogue binary operation performs operation on
 * source (the left argument) and target (the right argument) catalogues.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractOperation implements OperationInterface
{
    protected $source;
    protected $target;
    protected $result;

    /**
     * @var array|null The domains affected by this operation
     */
    private $domains;

    /**
     * This array stores 'all', 'new' and 'obsolete' messages for all valid domains.
     *
     * The data structure of this array is as follows:
     *
     *     [
     *         'domain 1' => [
     *             'all' => [...],
     *             'new' => [...],
     *             'obsolete' => [...]
     *         ],
     *         'domain 2' => [
     *             'all' => [...],
     *             'new' => [...],
     *             'obsolete' => [...]
     *         ],
     *         ...
     *     ]
     *
     * @var array The array that stores 'all', 'new' and 'obsolete' messages
     */
    protected $messages;

    /**
     * @throws LogicException
     */
    public function __construct(MessageCatalogueInterface $source, MessageCatalogueInterface $target)
    {
        if ($source->getLocale() !== $target->getLocale()) {
            throw new LogicException('Operated catalogues must belong to the same locale.');
        }

        $this->source = $source;
        $this->target = $target;
        $this->result = new MessageCatalogue($source->getLocale());
        $this->messages = [];
    }

    /**
     * {@inheritdoc}
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
     */
    public function getMessages(string $domain)
    {
        if (!\in_array($domain, $this->getDomains())) {
            throw new InvalidArgumentException(sprintf('Invalid domain: "%s".', $domain));
        }

        if (!isset($this->messages[$domain]['all'])) {
            $this->processDomain($domain);
        }

        return $this->messages[$domain]['all'];
    }

    /**
     * {@inheritdoc}
     */
    public function getNewMessages(string $domain)
    {
        if (!\in_array($domain, $this->getDomains())) {
            throw new InvalidArgumentException(sprintf('Invalid domain: "%s".', $domain));
        }

        if (!isset($this->messages[$domain]['new'])) {
            $this->processDomain($domain);
        }

        return $this->messages[$domain]['new'];
    }

    /**
     * {@inheritdoc}
     */
    public function getObsoleteMessages(string $domain)
    {
        if (!\in_array($domain, $this->getDomains())) {
            throw new InvalidArgumentException(sprintf('Invalid domain: "%s".', $domain));
        }

        if (!isset($this->messages[$domain]['obsolete'])) {
            $this->processDomain($domain);
        }

        return $this->messages[$domain]['obsolete'];
    }

    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        foreach ($this->getDomains() as $domain) {
            if (!isset($this->messages[$domain])) {
                $this->processDomain($domain);
            }

            $this->processVariablesMetadata($domain);
        }

        return $this->result;
    }

    /**
     * Variables metadata should be updated according to target.
     */
    protected function processVariablesMetadata(string $domain)
    {
        $intlDomain = $domain.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;

        foreach ($this->target->all($domain) as $id => $message) {
            if ($this->source->has($id, $domain)) {
                $targetDomain = $this->target->defines($id, $intlDomain) ? $intlDomain : $domain;
                $sourceDomain = $this->source->defines($id, $intlDomain) ? $intlDomain : $domain;
                $sourceMetadata = $this->result->getMetadata($id, $sourceDomain) ?? [];
                $variablesNote = null;

                // Get target variables note
                foreach ($this->target->getMetadata($id, $targetDomain)['notes'] ?? [] as $note) {
                    if (isset($note['category']) && MessageCatalogue::METADATA_AVAILABLE_VARIABLES_KEY === $note['category']) {
                        $variablesNote = $note;

                        break;
                    }
                }

                if ($variablesNote) {
                    // Update old variables note (if any)
                    if (isset($sourceMetadata['notes'])) {
                        foreach ($sourceMetadata['notes'] as $index => $note) {
                            if (isset($note['category']) && MessageCatalogue::METADATA_AVAILABLE_VARIABLES_KEY === $note['category']) {
                                $sourceMetadata['notes'][$index] = $variablesNote;

                                break;
                            }
                        }
                    } else {
                        $sourceMetadata['notes'][] = $variablesNote;
                    }

                    $this->result->setMetadata($id, $sourceMetadata, $sourceDomain);
                }
            }
        }
    }

    /**
     * Performs operation on source and target catalogues for the given domain and
     * stores the results.
     *
     * @param string $domain The domain which the operation will be performed for
     */
    abstract protected function processDomain(string $domain);
}
