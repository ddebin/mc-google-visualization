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
     * @param mixed $value
     */
    public function __construct($value, ?string $name)
    {
        $this->value = $value;
        $this->name = $name;
    }

    public function getValues(): array
    {
        return [$this->value];
    }

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
