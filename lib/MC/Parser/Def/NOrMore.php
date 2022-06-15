<?php

declare(strict_types = 1);

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\ParseError;

class NOrMore extends Def
{
    /** @var Def */
    public $expr;

    /** @var int */
    public $min;

    public function __construct(Def $expr, int $min)
    {
        $this->expr = $expr;
        $this->min = $min;
    }

    /**
     * @throws ParseError
     */
    public function _parse(string $str, int $loc): array
    {
        $toks = $this->tokenGroup();

        try {
            /** @phpstan-ignore-next-line */
            while (true) {
                [$loc, $tok] = $this->expr->parsePart($str, $loc);
                $toks->append($tok);
            }
        } catch (ParseError $parseError) {
            // Ignore parsing errors - that just means we're done
        }

        if ($toks->count() < $this->min) {
            throw new ParseError('Expected: '.$this->min.' or more '.$this->expr->name, $str, $loc);
        }

        if (0 === $toks->count()) {
            // If this token is empty, remove it from the result group
            $toks = null;
        }

        return [$loc, $toks];
    }

    public function _name(): string
    {
        return $this->min.' or more: '.$this->expr->getName();
    }
}
