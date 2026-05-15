@extends('admin.layout')

@section('title', 'Admin Login')

@section('content')
<div class="max-w-md mx-auto mt-16 bg-white rounded-lg shadow p-8">
    <h1 class="text-2xl font-bold mb-6">Admin Login</h1>

    <form method="POST" action="{{ route('admin.login.attempt') }}">
        @csrf

        <div class="mb-4">
            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="{{ old('username') }}"
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('username') border-red-500 @enderror"
            >
            @error('username')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
            Sign In
        </button>
    </form>
</div>
@endsection
