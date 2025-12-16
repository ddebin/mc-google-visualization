<?php

declare(strict_types = 1);

namespace MC\Google;

class Visualization_QueryError extends Visualization_Error
{
    public string $type = 'invalid_query';

    public string $summary = 'Invalid Query';
}
