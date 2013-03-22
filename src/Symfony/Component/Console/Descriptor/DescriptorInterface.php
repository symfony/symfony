<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
interface DescriptorInterface
{
    /**
     * Returns given object's representation.
     *
     * @param object  $object The object to describe
     * @param boolean $raw    No additional markers if true
     *
     * @return string The object formatted description
     */
    public function describe($object, $raw = false);

    /**
     * Tests if this descriptor supports given object.
     *
     * @param object $object The object to describe
     *
     * @return boolean
     */
    public function supports($object);

    /**
     * Returns descriptor's format name.
     *
     * @return string The format name
     */
    public function getFormat();

    /**
     * Returns true if output formatting is used.
     *
     * @return boolean
     */
    public function useFormatting();
}
