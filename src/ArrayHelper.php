<?php
/**
 *
 */

namespace JPry\VVVBase;


class DefaultsArray implements \ArrayAccess
{
    protected $data;
    protected $defaults;

    /**
     * ArrayHelper constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = (array) $data;
    }

    /**
     * Determine if a specific key exists in the array.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Get a particular key from the array.
     *
     * If a default has been set for that key and the key was not found, the default will be returned instead.
     *
     * @param mixed $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->data[$offset];
        }

        if (array_key_exists($offset, $this->defaults)) {
            return $this->defaults[$offset];
        }

        return null;
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }


    public function setDefault($offset, $value)
    {
        $this->defaults[$offset] = $value;
    }


    public function setDefaults($defaults)
    {
        $this->defaults = (array) $defaults;
    }
}
