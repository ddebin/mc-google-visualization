<?php

declare(strict_types = 1);

namespace MC\Parser;

/**
 * An instance of a parsed token.
 */
class Token
{
    public ?string $name;

    public ?string $value;

    /**
     * Token constructor.
     */
    public function __construct(?string $value, ?string $name)
    {
        $this->value = $value;
        $this->name = $name;
    }

    /**
     * @return array<null|string>
     */
    public function getValues(): array
    {
        return [$this->value];
    }

    /**
     * @return array<array{null|string, null|string}>
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
