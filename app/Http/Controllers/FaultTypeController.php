<?php

namespace App\Http\Controllers;

use App\Models\FaultType;

class FaultTypeController extends LookupController
{
    protected string $model = FaultType::class;

    protected string $routePrefix = 'fault-types';

    protected string $titleKey = 'FaultTypes';
}
