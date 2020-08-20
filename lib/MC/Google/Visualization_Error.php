<?php

declare(strict_types = 1);

namespace MC\Google;

use Exception;

class Visualization_Error extends Exception
{
    /** @var string */
    public $type = 'server_error';

    /** @var string */
    public $summary = 'Server Error';
}
