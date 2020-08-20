<?php

namespace MC\Parser;

class ParseError extends Error
{
    /** @var string */
    public $data;

    /** @var int */
    public $loc;

    public function __construct(string $msg, string $str, int $loc)
    {
        $this->data = $str;
        $this->loc = $loc;
        parent::__construct($msg);
    }
}
