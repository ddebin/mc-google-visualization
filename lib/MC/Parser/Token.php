<?php

namespace MC\Parser;

/**
 * An instance of a parsed token.
 */
class Token
{
    public $name;
    public $value;

    /**
     * Token constructor.
     *
     * @param mixed       $value
     * @param null|string $name
     */
    public function __construct($value, $name = null)
    {
        $this->value = $value;
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return [$this->value];
    }

    /**
     * @return array
     */
    public function getNameValues()
    {
        return [[$this->name, $this->value]];
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return false;
    }

    /**
     * @return Token[]
     */
    public function getChildren()
    {
        return [];
    }
}
