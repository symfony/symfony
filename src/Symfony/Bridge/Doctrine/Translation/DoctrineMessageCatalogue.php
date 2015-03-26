<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Translation;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\MultiGetCache;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class DoctrineMessageCatalogue extends MessageCatalogue
{
    const PREFIX = 'sf2_translation';
    const CATALOGUE_DOMAINS = 'domains';
    const CATALOGUE_DOMAIN_METADATA = 'domain_meta_';

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var array
     */
    private $domains = array();

    /**
     * @param string $locale
     * @param Cache  $cache
     * @param string $prefix
     */
    public function __construct($locale, Cache $cache, $prefix = self::PREFIX)
    {
        parent::__construct($locale);
        if (0 === strlen($prefix)) {
            throw new \InvalidArgumentException('$prefix cannot be empty.');
        }

        $this->cache = $cache;
        $this->prefix = $prefix.'_'.$locale.'_';

        if ($cache->contains($domainsId = $this->prefix.self::CATALOGUE_DOMAINS)) {
            $this->domains = $cache->fetch($domainsId);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDomains()
    {
        return $this->domains;
    }

    /**
     * {@inheritdoc}
     */
    public function all($domain = null)
    {
        if (!$this->cache instanceof MultiGetCache) {
            return array();
        }

        $domains = $this->domains;
        if (null !== $domain) {
            $domains = array($domain);
        }

        $messages = array();
        foreach ($domains as $domainMeta) {
            $domainIdentity =  $this->getDomainMetaDataId($domainMeta);
            if ($this->cache->contains($domainIdentity)) {
                $keys = $this->cache->fetch($domainIdentity);
                $values = $this->cache->fetchMultiple(array_keys($keys));
                foreach ($keys as $key => $id) {
                    if (isset($values[$key])) {
                        $messages[$domainMeta][$id] = $values[$key];
                    }
                }
            }
        }

        if (null === $domain) {
            return $messages;
        }

        return isset($messages[$domain]) ? $messages[$domain] : array();
    }

    /**
     * {@inheritdoc}
     */
    public function set($id, $translation, $domain = 'messages')
    {
        $this->add(array($id => $translation), $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function has($id, $domain = 'messages')
    {
        if ($this->defines($id, $domain)) {
            return true;
        }

        $fallbackCatalogue = $this->getFallbackCatalogue();
        if (null !== $fallbackCatalogue) {
            return $fallbackCatalogue->has($id, $domain);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function defines($id, $domain = 'messages')
    {
        $key = $this->getCacheId($domain, $id);

        return $this->cache->contains($key);
    }

    /**
     * {@inheritdoc}
     */
    public function get($id, $domain = 'messages')
    {
        if ($this->defines($id, $domain)) {
            return $this->cache->fetch($this->getCacheId($domain, $id));
        }

        $fallbackCatalogue = $this->getFallbackCatalogue();
        if (null !== $fallbackCatalogue) {
            return $fallbackCatalogue->get($id, $domain);
        }

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($messages, $domain = 'messages')
    {
        $domainMetaData = array();
        $domainMetaKey = $this->getDomainMetaDataId($domain);
        if ($this->cache->contains($domainMetaKey)) {
            $domainMetaData = $this->cache->fetch($domainMetaKey);
        }

        foreach ($domainMetaData as $key => $id) {
            if (!isset($messages[$id])) {
                unset($domainMetaData[$key]);
                $this->cache->delete($key);
            }
        }

        $this->cache->save($domainMetaKey, $domainMetaData);
        $this->add($messages, $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function add($messages, $domain = 'messages')
    {
        if (!isset($this->domains[$domain])) {
            $this->addDomain($domain);
        }

        $domainMetaData = array();
        $domainMetaKey = $this->getDomainMetaDataId($domain);
        if ($this->cache->contains($domainMetaKey)) {
            $domainMetaData = $this->cache->fetch($domainMetaKey);
        }

        foreach ($messages as $id => $translation) {
            $key = $this->getCacheId($domain, $id);
            $domainMetaData[$key] = $id;
            $this->cache->save($key, $translation);
        }

        $this->addDomainMetaData($domain, $domainMetaData);
    }

    private function addDomain($domain)
    {
        $this->domains[] = $domain;
        $this->cache->save($this->prefix.self::CATALOGUE_DOMAINS, $this->domains);
    }

    private function addDomainMetaData($domain, $keys = array())
    {
        $domainIdentity = $this->getDomainMetaDataId($domain);
        $this->cache->save($domainIdentity, $keys);
    }

    private function getCacheId($id, $domain = 'messages')
    {
        return $this->prefix.$domain.'_'.sha1($id);
    }

    private function getDomainMetaDataId($domain)
    {
        return $this->prefix.self::CATALOGUE_DOMAIN_METADATA.$domain;
    }
}
