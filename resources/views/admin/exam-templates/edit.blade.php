@extends('layouts.admin')

@section('title', 'Modifier ' . $examTemplate->title . ' — ' . $hospital->name)

@section('admin-content')
<a href="{{ route('admin.hospitals.exam-templates.index', $hospital) }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; {{ $hospital->name }}</a>
<h1 class="mb-6 mt-1 text-xl font-semibold text-slate-900">Modifier « {{ $examTemplate->title }} »</h1>

<div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
    <form method="POST" action="{{ route('admin.hospitals.exam-templates.update', [$hospital, $examTemplate]) }}" class="space-y-6">
        @csrf
        @method('PUT')
        @include('admin.exam-templates._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.hospitals.exam-templates.index', $hospital) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Annuler</a>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
