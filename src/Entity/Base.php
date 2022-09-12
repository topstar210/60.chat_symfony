<?php

namespace App\Entity;

use App\Utils\Inflector;

/**
 * Storage agnostic model object.
 */
abstract class Base
{
    /**
     * Magic getter to allow access like $entry->foo to call $entry->getFoo().
     *
     * Alternatively, if no getFoo() is defined, but a $_foo protected variable
     * is defined, this is returned.
     *
     * @param string $name The variable name sought
     */
    public function __get($name)
    {
        $method = 'get'.ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        $name = Inflector::tableize($name);
        if (property_exists($this, $name)) {
            return $this->$name;
        } else {
            throw new \InvalidArgumentException(sprintf('Property %s::%s does not exist.', get_class($this), $name));
        }
    }

    /**
     * Magic setter to allow acces like $entry->foo='bar' to call
     * $entry->setFoo('bar') automatically.
     *
     * Alternatively, if no setFoo() is defined, but a $_foo protected variable
     * is defined, this is returned.
     *
     * @param string $name
     * @param string $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $method = 'set'.ucfirst($name);
        if (method_exists($this, $method)) {
            $this->$method($value);
        }

        $name = Inflector::tableize($name);
        if (isset($this->$name) || ($this->$name === null)) {
            if (is_string($value)) {
                $value = stripcslashes(strip_tags($value));
            }
            $this->$name = $value;
        } else {
            throw new \InvalidArgumentException(sprintf('Property %s::%s does not exist.', get_class($this), $name));
        }
    }

    /**
     * Checks whether or not a property exist.
     *
     * @param string $name
     *
     * @return boolean
     */
    public function has($name)
    {
        $name = Inflector::tableize($name);

        return isset($this->$name);
    }

    /**
     * Adds support for magic finders.
     *
     * @return array|object The found entity/entities.
     *
     * @throws BadMethodCallException  If the method called is not found and
     *                                 therefore an invalid method call.
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $arguments);
        }

        if (preg_match('/^get(.+)$/', $method, $match)) {
            return $this->__get(ucfirst($match[1]));
        } elseif (preg_match('/^set(.+)$/', $method, $match)) {
            return $this->__set(ucfirst($match[1]), $arguments[0]);
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s.', get_class($this), $method));
    }
}
