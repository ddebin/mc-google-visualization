<?php

declare(strict_types = 1);

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\ParseError;

/**
 * Successfully match one of a set of potential expressions - the longest match wins if there are multiples.
 */
class OneOf extends Def
{
    /** @var Def[] */
    public $exprs = [];

    /**
     * OneOf constructor.
     *
     * @param mixed[] $exprs
     */
    public function __construct(array $exprs = [])
    {
        $this->exprs = $exprs;
    }

    /**
     * @param string $str the string to parse
     * @param int    $loc the index to start parsing
     *
     * @throws ParseError
     *
     * @return mixed[]
     */
    public function _parse(string $str, int $loc): array
    {
        $maxMatch = -1;
        $res = null;
        foreach ($this->exprs as $expr) {
            try {
                list($loc2, $toks) = $expr->parsePart($str, $loc);
                if ($loc2 > $maxMatch) {
                    $maxMatch = $loc2;
                    $res = $toks;
                }
            } catch (ParseError $parseError) {
                //Ignore any non-matching conditions
            }
        }

        if (-1 === $maxMatch) {
            throw new ParseError('No alternative options match', $str, $loc);
        }
        if ((null !== $res) && (null === $res->name) && (null !== $this->name)) {
            $res->name = $this->name;
        }

        return [$maxMatch, $res];
    }

    public function _name(): string
    {
        $names = [];
        foreach ($this->exprs as $expr) {
            $names[] = $expr->getName();
        }

        return 'one of ('.implode(', ', $names).')';
    }
}
