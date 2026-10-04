<?php

namespace App\Http\Controllers;

use App\Models\Priority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriorityController extends LookupController
{
    protected string $model = Priority::class;

    protected string $routePrefix = 'priorities';

    protected string $titleKey = 'Priorities';

    protected function newModel(): Model
    {
        return new Priority([
            'is_active' => true,
            'show_in_quick' => true,
            'color' => '#0d6efd',
            'rank' => ((int) Priority::query()->max('rank')) + 1,
        ]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Model $item): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:60'],
            'name_ar' => ['required', 'string', 'max:60'],
            'hint_en' => ['nullable', 'string', 'max:120'],
            'hint_ar' => ['nullable', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'rank' => ['required', 'integer', 'min:0', 'max:99'],
        ]);
        foreach (['is_default', 'is_critical', 'show_in_quick', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        if ($data['is_default']) {
            $data['is_active'] = true;
            DB::table('priorities')->when($item, fn ($q) => $q->whereKeyNot($item->getKey()))->update(['is_default' => false]);
        }

        return $data;
    }
}
