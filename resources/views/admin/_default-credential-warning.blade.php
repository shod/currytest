@if(app(\App\Services\Currency\DefaultCredentialWatcher::class)->shouldWarn())
<div role="alert" class="bg-red-600 text-white px-4 py-3 text-sm font-medium">
    ⚠️ Warning: The admin account is still using the default MVP password. Change <code>ADMIN_PASSWORD</code> in your <code>.env</code> file immediately before exposing this application beyond local development.
</div>
@endif
