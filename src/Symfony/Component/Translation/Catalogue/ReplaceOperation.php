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

use Symfony\Component\Translation\MetadataAwareInterface;

/**
 * This will merge and replace all values in $target with values from $source.
 * It is the equivalent of running array_merge($target, $source). When in conflict,
 * always take values from $source.
 *
 * This operation is metadata aware. It will do the same recursive merge on metadata.
 *
 * all = source ∪ target = {x: x ∈ source ∨ x ∈ target}
 * new = all ∖ target = {x: x ∈ source ∧ x ∉ target}
 * obsolete = all ∖ source = {x: x ∈ target ∧ x ∉ source}
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ReplaceOperation extends AbstractOperation
{
    protected function processDomain($domain)
    {
        $this->messages[$domain] = array(
            'all' => array(),
            'new' => array(),
            'obsolete' => array(),
        );
        $sourceMessages = $this->source->all($domain);

        foreach ($this->target->all($domain) as $id => $message) {
            $this->messages[$domain]['all'][$id] = $message;

            // If $id is NOT defined in source.
            if (!array_key_exists($id, $sourceMessages)) {
                $this->messages[$domain]['obsolete'][$id] = $message;
            }
        }

        foreach ($sourceMessages as $id => $message) {
            if (!empty($message)) {
                $this->messages[$domain]['all'][$id] = $message;
            }

            if (!$this->target->has($id, $domain)) {
                $this->messages[$domain]['new'][$id] = $message;

                // Make sure to add it to the source if even if empty($message)
                $this->messages[$domain]['all'][$id] = $message;
            }
        }

        $this->result->add($this->messages[$domain]['all'], $domain);

        $targetMetadata = $this->target instanceof MetadataAwareInterface ? $this->target->getMetadata('', $domain) : array();
        $sourceMetadata = $this->source instanceof MetadataAwareInterface ? $this->source->getMetadata('', $domain) : array();
        $resultMetadata = $this->mergeMetaData($sourceMetadata, $targetMetadata);

        // Write back metadata
        foreach ($resultMetadata as $id => $data) {
            $this->result->setMetadata($id, $data, $domain);
        }
    }

    /**
     * @param array|null $source
     * @param array|null $target
     *
     * @return array
     */
    private function mergeMetadata($source, $target)
    {
        if (empty($source) && empty($target)) {
            return array();
        }

        if (empty($source)) {
            return $target;
        }

        if (empty($target)) {
            return $source;
        }

        if (!is_array($source) || !is_array($target)) {
            return $source;
        }

        $result = $this->doMergeMetadata($source, $target);

        return $result;
    }

    private function doMergeMetadata(array $source, array $target)
    {
        $isTargetArrayAssociative = $this->isArrayAssociative($target);
        foreach ($target as $key => $value) {
            if ($isTargetArrayAssociative) {
                if (isset($source[$key]) && $source[$key] !== $value) {
                    if (is_array($source[$key]) && is_array($value)) {
                        // If both arrays, do recursive call
                        $source[$key] = $this->doMergeMetadata($source[$key], $value);
                    }
                    // Else, use value form $source
                } else {
                    // Add new value
                    $source[$key] = $value;
                }
                // if sequential
            } elseif (!in_array($value, $source)) {
                $source[] = $value;
            }
        }

        return $source;
    }

    public function isArrayAssociative(array $arr)
    {
        if (array() === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
