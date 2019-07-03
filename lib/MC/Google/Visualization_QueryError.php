<?php

namespace MC\Google;

class Visualization_QueryError extends Visualization_Error
{
    public $type = 'invalid_query';
    public $summary = 'Invalid Query';
}
