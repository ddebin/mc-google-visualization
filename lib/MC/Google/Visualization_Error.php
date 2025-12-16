<?php

declare(strict_types = 1);

namespace MC\Google;

use Exception;

class Visualization_Error extends Exception
{
    public string $type = 'server_error';

    public string $summary = 'Server Error';
}
