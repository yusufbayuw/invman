@php
    $isLogin = request()->is('admin/login');
    $imgClass = $isLogin ? 'w-52' : 'w-12';
@endphp

<img src="{{ asset('images/app/bg-main.png') }}" alt="" class="{{ $imgClass }}">