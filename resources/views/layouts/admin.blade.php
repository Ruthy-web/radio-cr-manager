@extends('layouts.app')

@section('content')
<div class="min-h-screen">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('admin.dashboard') }}" class="font-semibold text-slate-900">Radio CR Manager</a>
            <nav class="flex items-center gap-4 text-sm text-slate-600">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-900">Tableau de bord</a>
                @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin))
                    <a href="{{ route('admin.hospitals.index') }}" class="hover:text-slate-900">Hôpitaux</a>
                @endif
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-100">
                        Se déconnecter
                    </button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('admin-content')
    </main>
</div>
@endsection
