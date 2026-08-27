@php
    $isLogin = request()->is('admin/login');
    $imgClass = $isLogin ? 'w-52' : 'w-12';
@endphp

<img src="{{ asset(config('app.logo')) }}" alt="Logo {{ config('app.name') }}" class="{{ $imgClass }}">
