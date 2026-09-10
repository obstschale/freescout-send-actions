@php
    $selected = old('sendactions_present') ? \Modules\SendActions\Providers\SendActionsServiceProvider::normalize(old('sendactions', [])) : $selected;
@endphp
<div class="form-group">
    <div class="col-sm-2 control-label">{{ __('Send Reply') }} / {{ __('Add Note') }}</div>
    <div class="col-sm-6">
        <fieldset class="sendactions-settings" aria-describedby="sendactions-help">
            <legend>{{ __('After Sending') }}</legend>
            <input type="hidden" name="sendactions_present" value="1">
            @foreach ($options as $option => $label)
                <div class="checkbox">
                    <label><input type="checkbox" name="sendactions[]" value="{{ $option }}" @if (in_array($option, $selected, true))checked="checked"@endif> {{ $label }}</label>
                </div>
            @endforeach
            <p class="help-block" id="sendactions-help">{{ app()->getLocale() === 'de' ? 'Angehakte Optionen ersetzen den normalen Sendebutton durch Direktbuttons für Antworten und Notizen. Auch die Weiterleitungsoptionen im Dropdown senden dann direkt ab. Ohne Auswahl bleibt die Standardansicht erhalten.' : 'Checked options replace the standard send button with direct buttons for replies and notes. Redirect options in the dropdown then send immediately as well. Leave all unchecked to keep the standard layout.' }}</p>
        </fieldset>
    </div>
</div>
