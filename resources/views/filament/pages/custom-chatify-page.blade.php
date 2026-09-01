@php
    $requestedContactId = request()->integer('contact');
    $initialContactId = $requestedContactId > 0
        && $requestedContactId !== (int) Auth::id()
        && \App\Models\User::query()->whereKey($requestedContactId)->exists()
            ? $requestedContactId
            : 0;
@endphp

<div class="chatify-page-shell">
    @include('Chatify::pages.app', [
        'id' => $initialContactId,
        'messengerColor' => Auth::user()->messenger_color ?: \Chatify\Facades\ChatifyMessenger::getFallbackColor(),
        'dark_mode' => Auth::user()->dark_mode < 1 ? 'light' : 'dark',
    ])

<style>
    .chatify-page-shell {
        position: relative;
        width: 100%;
        height: calc(100dvh - 8rem);
        min-height: 20rem;
        overflow: hidden;
        border: 1px solid rgb(229 231 235);
        border-radius: .75rem;
        background: var(--primary-bg-color, #fff);
    }

    .chatify-page-shell .messenger,
    .chatify-page-shell .messenger-listView,
    .chatify-page-shell .messenger-messagingView,
    .chatify-page-shell .messenger-infoView {
        height: 100%;
        min-height: 0;
    }

    .chatify-page-shell .messenger-listView {
        overflow: hidden;
    }

    .chatify-page-shell .contacts-container,
    .chatify-page-shell .messenger-tab {
        min-height: 0;
    }

    .chatify-page-shell .contacts-container {
        flex: 1 1 auto;
        overflow: hidden;
    }

    .chatify-page-shell .messenger-tab {
        height: 100%;
    }

    .chatify-page-shell .messenger-messagingView .m-body {
        flex: 1 1 auto;
        height: auto;
        min-height: 0;
    }

    .chatify-page-shell .messenger-sendCard {
        flex: 0 0 auto;
        margin-bottom: 10px;
    }

    .chatify-page-shell .avatar {
        flex: 0 0 auto;
        border-color: rgb(226 232 240);
        background-color: rgb(241 245 249);
    }

    @media (max-width: 1060px) {
        .chatify-page-shell .messenger-infoView {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
        }
    }

    @media (max-width: 980px) {
        .chatify-page-shell .messenger-listView {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
        }
    }

    @media (max-width: 680px) {
        .chatify-page-shell {
            border-radius: .5rem;
        }

        .chatify-page-shell .messenger-messagingView {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
        }
    }
</style>

<script>
    (() => {
        const resizeChatifyPage = () => {
            const shell = document.querySelector('.chatify-page-shell');

            if (! shell) {
                return;
            }

            const viewportHeight = window.visualViewport?.height ?? window.innerHeight;
            const availableHeight = viewportHeight - Math.max(shell.getBoundingClientRect().top, 0) - 16;

            shell.style.height = `${Math.max(320, availableHeight)}px`;
        };

        requestAnimationFrame(resizeChatifyPage);
        window.addEventListener('resize', resizeChatifyPage, { passive: true });
        window.visualViewport?.addEventListener('resize', resizeChatifyPage, { passive: true });
        document.addEventListener('livewire:navigated', resizeChatifyPage);
    })();
</script>
</div>
