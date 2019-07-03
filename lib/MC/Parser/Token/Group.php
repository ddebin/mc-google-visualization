<?php

namespace MC\Parser\Token;

use Countable;
use MC\Parser\Token;

class Group extends Token implements Countable
{
    /** @var Token[] */
    public $subtoks = [];

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

    public function count()
    {
        return count($this->subtoks);
    }

    public function getValues()
    {
        $values = [];
        foreach ($this->subtoks as $tok) {
            $values = array_merge($values, $tok->getValues());
        }

        return $values;
    }

    public function getNameValues()
    {
        $values = [];
        foreach ($this->subtoks as $tok) {
            $values = array_merge($values, $tok->getNameValues());
        }

        return $values;
    }

    public function hasChildren()
    {
        return !empty($this->subtoks);
    }

    public function getChildren()
    {
        return $this->subtoks;
    }
}
