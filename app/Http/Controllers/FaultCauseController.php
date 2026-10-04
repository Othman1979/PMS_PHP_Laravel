<?php

namespace App\Http\Controllers;

use App\Models\FaultCause;

class FaultCauseController extends LookupController
{
    protected string $model = FaultCause::class;

    protected string $routePrefix = 'fault-causes';

    protected string $titleKey = 'FaultCauses';
}
