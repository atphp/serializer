<?php

namespace AndyTruong\Serializer;

use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Trait provide fromArray() and toArray methods, simple way for serialization.
 */
trait SerializableTrait
{

    /**
     * Camelizes a given string.
     *
     * @param  string $string Some string
     *
     * @return string The camelized version of the string
     */
    private function camelize($string)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) {
            return ('.' === $match[1] ? '_' : '') . strtoupper($match[2]);
        }, $string);
    }

    /**
     * Get a property in object.
     *
     * @param string $pty
     * @return mixed
     */
    protected function getPropertyValue($pty, $includeNull, $maxNesting)
    {
        $return = $this->{$pty};

        $camelPty = $this->camelize($pty);
        $rClass = new ReflectionClass($this);
        foreach (array('get', 'is', 'has') as $prefix) {
            $method = $prefix . $camelPty;
            if ($rClass->hasMethod($method) && $rClass->getMethod($method)->isPublic() && !count($rClass->getMethod($method)->getParameters())) {
                $return = $this->{$method}();
                break;
            }
        }

        if (is_object($return) && method_exists($return, 'toArray')) {
            if (($this !== $return) && ($maxNesting > 0)) {
                $return = $return->toArray($includeNull, $maxNesting - 1);
            }
        }

        return $return;
    }

    /**
     * Set property.
     *
     * @param string $pty
     * @param mixed $value
     * @throws \RuntimeException
     */
    public function setPropertyValue($pty, $value)
    {
        $method = 'set' . $this->camelize($pty);
        $rClass = new ReflectionClass($this);

        if ($rClass->hasMethod($method) && $rClass->getMethod($method)->isPublic()) {
            if (is_array($value) && $typeHint = $rClass->getMethod($method)->getParameters()[0]->getClass()) {
                if (method_exists($typeHint->getName(), 'fromArray')) {
                    $value = call_user_func([$typeHint->getName(), 'fromArray'], $value);
                }
            }
            $this->{$method}($value);
        }
        elseif ($rClass->hasProperty($pty) && $rClass->getProperty($pty)->isPublic()) {
            $this->{$pty} = $value;
        }
        else {
            throw new RuntimeException(sprintf('Object.%s is not writable.', $pty));
        }
    }

    /**
     * @return ReflectionProperty[]
     */
    protected function getReflectionProperties()
    {
        return (new ReflectionClass($this))->getProperties();
    }

    /**
     * Represent object as array.
     *
     * @param boolean $includeNull
     * @return array
     */
    public function toArray($includeNull = false, $maxNesting = 3)
    {
        $array = array();

        foreach ($this->getReflectionProperties() as $pty) {
            /* @var $pty ReflectionProperty */
            if ($pty->isStatic()) {
                continue;
            }

            $value = $this->getPropertyValue($pty->getName(), $includeNull, $maxNesting);
            if ((null !== $value) || ((null === $value) && $includeNull)) {
                $array[$pty->getName()] = $value;
            }
        }

        return $array;
    }

    /**
     * Simple fromArray factory.
     *
     * @param array $input
     * @return EntitiyTraitTest
     */
    public static function fromArray($input)
    {
        $me = new static();

        foreach ($input as $pty => $value) {
            if (null !== $value) {
                $me->setPropertyValue($pty, $value);
            }
        }

        return $me;
    }

    /**
     * Represent object in json format.
     *
     * @param boolean $include_null
     * @param int $options
     * @return string
     */
    public function toJSON($include_null = false, $options = 0)
    {
        return json_encode($this->toArray($include_null), $options);
    }

    /**
     * Create new object from json string.
     *
     * @param string $input
     * @return static
     */
    public static function fromJSON($input)
    {
        return static::fromArray(json_decode($input, true));
    }

}
