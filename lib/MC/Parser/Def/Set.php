<?php

declare(strict_types = 1);

namespace MC\Parser\Def;

use MC\Parser\Def;

/**
 * Verify that the string matches a series of subexpressions in the specified order.
 */
class Set extends Def
{
    /** @var array */
    public $exprs = [];

    /**
     * Set constructor.
     */
    public function __construct(array $exprs = [])
    {
        $this->exprs = $exprs;
    }

    /**
     * @param string $str the string to parse
     * @param int    $loc the index to start parsing
     */
    public function _parse(string $str, int $loc): array
    {
        $res = $this->tokenGroup();
        foreach ($this->exprs as $expr) {
            [$loc, $toks] = $expr->parsePart($str, $loc);
            if ((null !== $toks) && (!is_array($toks) || (count($toks) > 0))) {
                $res->append($toks);
            }
        }

        return [$loc, $res];
    }

    public function _name(): string
    {
        $names = [];
        foreach ($this->exprs as $expr) {
            $names[] = $expr->getName();
        }

        return '['.implode(', ', $names).']';
    }
}
