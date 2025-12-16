<?php

declare(strict_types = 1);

namespace MC\Parser;

class ParseError extends Error
{
    public string $data;

    public int $loc;

    public function __construct(string $msg, string $str, int $loc)
    {
        $this->data = $str;
        $this->loc = $loc;
        parent::__construct($msg);
    }
}
