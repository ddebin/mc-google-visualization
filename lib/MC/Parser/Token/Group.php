<?php

/** @noinspection SlowArrayOperationsInLoopInspection */

declare(strict_types = 1);

namespace MC\Parser\Token;

use Countable;
use MC\Parser\Token;

class Group extends Token implements Countable
{
    /** @var Token[] */
    public $subtoks = [];

    public function __construct(?string $name)
    {
        parent::__construct(null, $name);
    }

    /**
     * Append one or more tokens to this group.
     *
     * @param null|Token|Token[] $toks one or more token instances
     */
    public function append($toks): void
    {
        if (null === $toks) {
            return;
        }
        if (!is_array($toks)) {
            $toks = [$toks];
        }
        $this->subtoks = array_merge($this->subtoks, $toks);
    }

    public function count(): int
    {
        return count($this->subtoks);
    }

    /**
     * @return array<null|string>
     */
    public function getValues(): array
    {
        $values = [];
        foreach ($this->subtoks as $tok) {
            $values = array_merge($values, $tok->getValues());
        }

        return $values;
    }

    /**
     * @return array<array{null|string, null|string}>
     */
    public function getNameValues(): array
    {
        $values = [];
        foreach ($this->subtoks as $tok) {
            $values = array_merge($values, $tok->getNameValues());
        }

        return $values;
    }

    public function hasChildren(): bool
    {
        return count($this->subtoks) > 0;
    }

    /**
     * @return Token[]
     */
    public function getChildren(): array
    {
        return $this->subtoks;
    }
}
