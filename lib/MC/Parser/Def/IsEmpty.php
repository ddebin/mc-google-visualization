<?php

namespace MC\Parser\Def;

use MC\Parser\Def;

/**
 * This match always succeeds with a zero-length suppressed token - useful for doing any kind of optional matching.
 */
class IsEmpty extends Def
{
    /** @var bool */
    public $suppress = true;

    /**
     * @param string $str
     * @param int    $loc
     *
     * @return array
     */
    public function _parse(string $str, int $loc): array
    {
        return [$loc, $this->token(null)];
    }

    /**
     * @return string
     */
    public function _name(): string
    {
        return 'empty string';
    }
}
