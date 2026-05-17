<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiDto;
use App\Http\Controllers\Controller;

abstract class ApiController extends Controller
{
    use RespondsWithApiDto;
}
