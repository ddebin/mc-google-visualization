<?php

/** @noinspection SlowArrayOperationsInLoopInspection */

namespace MC\Parser\Token;

use Countable;
use MC\Parser\Token;

class Group extends Token implements Countable
{
    /** @var Token[] */
    public $subtoks = [];

    /**
     * @param string|null $name
     */
    public function __construct($name)
    {
        parent::__construct(null, $name);
    }

    /**
     * Append one or more tokens to this group.
     *
     * @param null|Token|Token[] $toks one or more token instances
     */
    public function append($toks)
    {
        if (null === $toks) {
            return;
        }
        if (!is_array($toks)) {
            $toks = [$toks];
        }
        $this->subtoks = array_merge($this->subtoks, $toks);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->subtoks);
    }

    /**
     * @return mixed[]
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
     * @return mixed[]
     */
    public function getNameValues(): array
    {
        $values = [];
        foreach ($this->subtoks as $tok) {
            $values = array_merge($values, $tok->getNameValues());
        }

        return $values;
    }

    /**
     * @return bool
     */
    public function hasChildren(): bool
    {
        return !empty($this->subtoks);
    }

    /**
     * @return Token[]
     */
    public function getChildren(): array
    {
        return $this->subtoks;
    }
}
