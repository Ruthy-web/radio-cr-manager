@extends('layouts.app')

@section('title', 'Vérification en deux étapes — Radio CR Manager')

@section('content')
<div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="mb-1 text-xl font-semibold text-slate-900">Vérification en deux étapes</h1>
        <p class="mb-6 text-sm text-slate-500">Un code à 6 chiffres vient de vous être envoyé par e-mail.</p>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.two-factor.verify') }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="mb-1 block text-sm font-medium text-slate-700">Code de vérification</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-center text-lg tracking-[0.5em] focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <button type="submit"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                Valider
            </button>
        </form>
    </div>
</div>
@endsection
