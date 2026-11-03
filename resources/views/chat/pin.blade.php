@component('layouts.chat', ['title' => 'Masuk'])
    <div class="chat-pin">
        <div class="chat-pin__card">
            <div class="chat-pin__mark" aria-hidden="true">
                <x-icon name="lock" :size="22" />
            </div>

            <h1 class="chat-pin__title">Ruang pribadi</h1>
            <p class="chat-pin__lede">Masukkan PIN 6 angka untuk melanjutkan.</p>

            <form method="POST" action="{{ route('chat.pin.submit') }}" class="chat-pin__form">
                @csrf

                <label class="sr-only" for="pin">PIN</label>
                <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*"
                    autocomplete="one-time-code" maxlength="6" required autofocus
                    class="chat-pin__input @error('pin') is-invalid @enderror" placeholder="••••••" />

                @error('pin')
                    <p class="chat-pin__error" role="alert">{{ $message }}</p>
                @enderror

                <button type="submit" class="chat-pin__submit">Masuk</button>
            </form>
        </div>
    </div>
@endcomponent
