<?php

namespace MC\Parser;

/**
 * An instance of a parsed token.
 */
class Token
{
    public $name;
    public $value;

    public function __construct($value, $name = null)
    {
        $this->value = $value;
        $this->name = $name;
    }

    public function getValues()
    {
        return [$this->value];
    }

    public function getNameValues()
    {
        return [[$this->name, $this->value]];
    }

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
