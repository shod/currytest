@extends('admin.layout')

@section('title', 'Available Currencies')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Available Currencies</h1>
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">Logout</button>
    </form>
</div>

<nav class="mb-4 flex gap-4">
    <a href="{{ route('admin.rates.index') }}" class="text-blue-600 hover:underline">Rates</a>
    <a href="{{ route('admin.currencies.index') }}" class="text-blue-600 hover:underline font-medium">Currencies</a>
</nav>

<table class="w-full bg-white rounded-lg shadow overflow-hidden">
    <thead class="bg-gray-100">
        <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Code</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Enabled</th>
        </tr>
    </thead>
    <tbody>
        @forelse($currencies as $currency)
            <tr class="border-t border-gray-200">
                <td class="px-4 py-3 text-sm font-mono">{{ $currency->code }}</td>
                <td class="px-4 py-3 text-sm">{{ $currency->name }}</td>
                <td class="px-4 py-3 text-sm">{{ $currency->is_enabled ? 'Yes' : 'No' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                    No currencies available. Run <code>php artisan db:seed</code> to seed the starter set.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection
