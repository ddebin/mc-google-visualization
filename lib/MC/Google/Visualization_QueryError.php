<?php

namespace MC\Google;

class Visualization_QueryError extends Visualization_Error
{
    /** @var string */
    public $type = 'invalid_query';

    /** @var string */
    public $summary = 'Invalid Query';
}
