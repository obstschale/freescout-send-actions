@php
    $icons = [
        \App\MailboxUser::AFTER_SEND_STAY => 'pushpin',
        \App\MailboxUser::AFTER_SEND_NEXT => 'arrow-right',
        \App\MailboxUser::AFTER_SEND_FOLDER => 'folder-open',
    ];
@endphp
<div class="sendactions-buttons" role="group" aria-label="{{ __('After Sending') }}">
    @foreach ($selected as $option)
        <button type="button" class="btn btn-primary sendactions-direct" data-after-send="{{ $option }}">
            <span class="glyphicon glyphicon-{{ $icons[$option] }}" aria-hidden="true"></span>
            <span class="sendactions-reply">{{ __('Send Reply') }}</span><span class="sendactions-note">{{ __('Add Note') }}</span> · {{ $options[$option] }}
        </button>
    @endforeach
</div>
