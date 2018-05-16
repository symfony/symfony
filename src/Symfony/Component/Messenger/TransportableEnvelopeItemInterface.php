<?php

namespace Symfony\Component\Messenger;

/**
 * An envelope item that could be transported.
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 *
 * @experimental in 4.1
 */
interface TransportableEnvelopeItemInterface extends EnvelopeItemInterface, \Serializable
{
}
