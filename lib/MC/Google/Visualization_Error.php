<?php

namespace MC\Google;

use Exception;

class Visualization_Error extends Exception
{
    public $type = 'server_error';
    public $summary = 'Server Error';
}
