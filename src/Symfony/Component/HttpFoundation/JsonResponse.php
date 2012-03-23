<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Response represents an HTTP response in JSON format.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class JsonResponse extends Response
{
    protected $data;
    protected $callback;

    /**
     * Constructor.
     *
     * @param mixed   $data     The response data
     * @param integer $status   The response status code
     * @param array   $headers  An array of response headers
     */
    public function __construct($data = array(), $status = 200, $headers = array())
    {
        parent::__construct('', $status, $headers);

        $this->setData($data);
    }

    /**
     * {@inheritDoc}
     */
    static public function create($data = array(), $status = 200, $headers = array())
    {
        return new static($data, $status, $headers);
    }

    /**
     * Sets the JSONP callback.
     *
     * @param string $callback
     *
     * @return JsonResponse
     */
    public function setCallback($callback = null)
    {
        if ($callback) {
            // taken from http://www.geekality.net/2011/08/03/valid-javascript-identifier/
            $pattern = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';
            if (!preg_match($pattern, $callback)) {
                throw new \InvalidArgumentException('The callback name is not valid.');
            }
        }

        $this->callback = $callback;

        return $this->update();
    }

    /**
     * Sets the data to be sent as json.
     *
     * @param mixed $data
     *
     * @return JsonResponse
     */
    public function setData($data = array())
    {
        // root should be JSON object, not array
        if (is_array($data) && 0 === count($data)) {
            $data = new \ArrayObject();
        }

        $this->data = json_encode($data);

        return $this->update();
    }

    /**
     * Updates the content and headers according to the json data and callback.
     *
     * @return JsonResponse
     */
    protected function update()
    {
        if ($this->callback) {
            // Not using application/javascript for compatibility reasons with older browsers.
            $this->headers->set('Content-Type', 'text/javascript', true);

            return $this->setContent(sprintf('%s(%s);', $this->callback, $this->data));
        }

        $this->headers->set('Content-Type', 'application/json', false);

        return $this->setContent($this->data);
    }
}
