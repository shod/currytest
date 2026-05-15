<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — @yield('title', 'Currency Module')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 min-h-screen">
    @include('admin._default-credential-warning')
    <div class="container mx-auto px-4 py-8">
        @yield('content')
    </div>
</body>
</html>
