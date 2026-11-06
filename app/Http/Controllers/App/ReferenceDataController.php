<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Models\District;
use App\Models\EducationCluster;
use App\Models\ExaminationType;
use App\Models\Parish;
use App\Models\SubCounty;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('reference-data.view');

        return view('administration.reference-data', [
            'title' => 'reference data',
            'breadcrumbs' => [['label' => 'administration'], ['label' => 'reference data']],
            'groups' => [
                'parishes' => Parish::query()->orderBy('name')->get(['name', 'code', 'is_active']),
                'districts' => District::query()->orderBy('name')->get(['name', 'code', 'is_active']),
                'sub-counties' => SubCounty::query()->orderBy('name')->get(['name', 'code', 'is_active']),
                'education clusters' => EducationCluster::query()->orderBy('name')->get(['name', 'code', 'is_active']),
                'examination types' => ExaminationType::query()->orderBy('name')->get(['name', 'slug', 'is_active']),
                'document categories' => DocumentCategory::query()->orderBy('name')->get(['name', 'slug', 'is_active']),
            ],
        ]);
    }
}
