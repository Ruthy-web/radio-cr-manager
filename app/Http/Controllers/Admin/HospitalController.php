<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HospitalRequest;
use App\Models\Hospital;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HospitalController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $hospitals = Hospital::withTrashed()
            ->withCount('examTemplates')
            ->orderBy('name')
            ->get();

        return view('admin.hospitals.index', compact('hospitals'));
    }

    public function create(): View
    {
        return view('admin.hospitals.create');
    }

    public function store(HospitalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $hospital = Hospital::create($data);
        $this->audit->log('hopital_cree', $request->user(), $hospital, $request);

        return redirect()
            ->route('admin.hospitals.index')
            ->with('status', "Hôpital « {$hospital->name} » créé.");
    }

    public function edit(Hospital $hospital): View
    {
        return view('admin.hospitals.edit', compact('hospital'));
    }

    public function update(HospitalRequest $request, Hospital $hospital): RedirectResponse
    {
        $hospital->update($request->validated());
        $this->audit->log('hopital_modifie', $request->user(), $hospital, $request);

        return redirect()
            ->route('admin.hospitals.index')
            ->with('status', "Hôpital « {$hospital->name} » mis à jour.");
    }

    /**
     * Désactivation (soft delete) — jamais de suppression physique (F2).
     */
    public function destroy(Hospital $hospital): RedirectResponse
    {
        $hospital->update(['active' => false]);
        $hospital->delete();
        $this->audit->log('hopital_desactive', request()->user(), $hospital, request());

        return redirect()
            ->route('admin.hospitals.index')
            ->with('status', "Hôpital « {$hospital->name} » désactivé.");
    }

    public function restore(int $hospital): RedirectResponse
    {
        $hospitalModel = Hospital::withTrashed()->findOrFail($hospital);
        $hospitalModel->restore();
        $hospitalModel->update(['active' => true]);
        $this->audit->log('hopital_reactive', request()->user(), $hospitalModel, request());

        return redirect()
            ->route('admin.hospitals.index')
            ->with('status', "Hôpital « {$hospitalModel->name} » réactivé.");
    }
}
