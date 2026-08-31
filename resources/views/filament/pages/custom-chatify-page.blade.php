<div>
    @include('Chatify::pages.app', [
        'id' => 0,
        'messengerColor' => Auth::user()->messenger_color ?: \Chatify\Facades\ChatifyMessenger::getFallbackColor(),
        'dark_mode' => Auth::user()->dark_mode < 1 ? 'light' : 'dark',
    ])
</div>
