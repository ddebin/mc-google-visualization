<?php

declare(strict_types = 1);

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\DefError;
use MC\Parser\ParseError;

class Recursive extends Def
{
    /** @var null|Def */
    public $replacement;

    /**
     * @throws DefError
     * @throws ParseError
     *
     * @return mixed[]
     */
    public function _parse(string $str, int $loc): array
    {
        if (null === $this->replacement) {
            throw new DefError('You must replace the recursive placeholder before parsing a grammar');
        }

        return $this->replacement->_parse($str, $loc);
    }

    /**
     * When actually parsing the grammar, use this rule to validate the recursive rule - this must be called before parsing begins.
     */
    public function replace(Def $expr): self
    {
        $this->replacement = $expr;

        return $this;
    }

    public function _name(): string
    {
        if (null === $this->replacement) {
            return 'placeholder';
        }

        return $this->replacement->getName();
    }
}
