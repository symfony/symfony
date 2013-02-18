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
    protected $prefix = '';

    /**
     * Constructor.
     *
     * @param mixed   $data    The response data
     * @param integer $status  The response status code
     * @param array   $headers An array of response headers
     */
    public function __construct($data = null, $status = 200, $headers = array())
    {
        parent::__construct('', $status, $headers);

        if (null === $data) {
            $data = new \ArrayObject();
        }
        $this->setData($data);
    }

    /**
     * {@inheritDoc}
     */
    public static function create($data = null, $status = 200, $headers = array())
    {
        return new static($data, $status, $headers);
    }

    /**
     * Sets the JSONP callback.
     *
     * @param string $callback
     *
     * @return JsonResponse
     *
     * @throws \InvalidArgumentException
     */
    public function setCallback($callback = null)
    {
        if (null !== $callback) {
            // taken from http://www.geekality.net/2011/08/03/valid-javascript-identifier/
            $pattern = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';
            $parts = explode('.', $callback);
            foreach ($parts as $part) {
                if (!preg_match($pattern, $part)) {
                    throw new \InvalidArgumentException('The callback name is not valid.');
                }
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
        // Encode <, >, ', &, and " for RFC4627-compliant JSON, which may also be embedded into HTML.
        $this->data = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return $this->update();
    }

    /**
     * Prepends the given prefix to the generated JSON.
     *
     * This could help mitigating CSRF attacks.
     * @see http://stackoverflow.com/questions/2669690
     *
     * @param string $prefix The prefix to use in front of the generated JSON
     *
     * @return JsonResponse
     */
    public function setPrefix($prefix = 'while(1);')
    {
        $this->prefix = $prefix;

        return $this->update();
    }

    /**
     * Updates the content and headers according to the json data and callback.
     *
     * @return JsonResponse
     */
    protected function update()
    {
        if (null !== $this->callback) {
            // Not using application/javascript for compatibility reasons with older browsers.
            $this->headers->set('Content-Type', 'text/javascript');

            return $this->setContent(sprintf('%s(%s);', $this->callback, $this->data));
        }

        // Only set the header when there is none or when it equals 'text/javascript' (from a previous update with callback)
        // in order to not overwrite a custom definition.
        if (!$this->headers->has('Content-Type') || in_array($this->headers->get('Content-Type'), array('application/json', 'text/javascript'))) {
            $this->headers->set('Content-Type', empty($this->prefix) ? 'application/json' : 'text/javascript');
        }

        return $this->setContent($this->prefix . $this->data);
    }
}
