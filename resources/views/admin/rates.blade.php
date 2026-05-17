@extends('admin.layout')

@section('title', 'Exchange Rates')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Exchange Rates</h1>
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">Logout</button>
    </form>
</div>

@if($lastRefresh)
    <p class="text-sm text-gray-500 mb-4">Last successful refresh: {{ $lastRefresh->finished_at->toIso8601String() }}</p>
@endif

<nav class="mb-4 flex gap-4">
    <a href="{{ route('admin.rates.index') }}" class="text-blue-600 hover:underline font-medium">Rates</a>
    <a href="{{ route('admin.currencies.index') }}" class="text-blue-600 hover:underline">Currencies</a>
</nav>

<table class="w-full bg-white rounded-lg shadow overflow-hidden">
    <thead class="bg-gray-100">
        <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Source</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Target</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Rate</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Fetched At</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rates as $rate)
            <tr class="border-t border-gray-200">
                <td class="px-4 py-3 text-sm">{{ $rate->base_code }}</td>
                <td class="px-4 py-3 text-sm">{{ $rate->targetCurrency->code ?? '—' }}</td>
                <td class="px-4 py-3 text-sm font-mono">{{ $rate->rate }}</td>
                <td class="px-4 py-3 text-sm text-gray-500">{{ $rate->fetched_at->toIso8601String() }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
                    No exchange rates stored. Run <code>php artisan currency:refresh-rates</code> to fetch rates.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection
