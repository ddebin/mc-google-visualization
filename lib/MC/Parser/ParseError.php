<?php

namespace MC\Parser;

class ParseError extends Error
{
    public $data;
    public $loc;

    public function __construct($msg, $str, $loc)
    {
        $this->data = $str;
        $this->loc = $loc;
        parent::__construct($msg);
    }
}
