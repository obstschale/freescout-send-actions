<div class="sendactions-buttons" role="group" aria-label="{{ __('After Sending') }}">
    @foreach ($selected as $option)
        <button type="button" class="btn btn-primary sendactions-direct" data-after-send="{{ $option }}">
            <span class="sendactions-reply">{{ __('Send Reply') }}</span><span class="sendactions-note">{{ __('Add Note') }}</span> · {{ $options[$option] }}
        </button>
    @endforeach
</div>
