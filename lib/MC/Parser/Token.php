<?php

declare(strict_types = 1);

namespace MC\Parser;

/**
 * An instance of a parsed token.
 */
class Token
{
    /** @var null|string */
    public $name;

    /** @var mixed */
    public $value;

    /**
     * Token constructor.
     *
     * @param mixed       $value
     * @param null|string $name
     */
    public function __construct($value, $name)
    {
        $this->value = $value;
        $this->name = $name;
    }

    /**
     * @return mixed[]
     */
    public function getValues(): array
    {
        return [$this->value];
    }

    /**
     * @return mixed[]
     */
    public function getNameValues(): array
    {
        return [[$this->name, $this->value]];
    }

    public function hasChildren(): bool
    {
        return false;
    }

    /**
     * @return Token[]
     */
    public function getChildren(): array
    {
        return [];
    }
}
