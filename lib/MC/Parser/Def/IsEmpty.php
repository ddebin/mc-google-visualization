<?php

namespace MC\Parser\Def;

use MC\Parser\Def;

/**
 * This match always succeeds with a zero-length suppressed token - useful for doing any kind of optional matching.
 */
class IsEmpty extends Def
{
    public $suppress = true;

    public function _parse($str, $loc)
    {
        return [$loc, $this->token(null)];
    }

    public function _name()
    {
        return 'empty string';
    }
}
