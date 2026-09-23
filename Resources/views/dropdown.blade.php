<li class="divider sendactions-menu-item" role="separator"></li>
<li class="sendactions-menu-item">
    <button type="button" class="sendactions-configure">{{ app()->getLocale() === 'de' ? 'Sendebuttons anpassen' : 'Customize send buttons' }}…</button>
    <div class="sendactions-modal-body hidden">
        <fieldset class="sendactions-settings">
            <legend>{{ app()->getLocale() === 'de' ? 'Welche Aktionen möchtest du als eigene Buttons anzeigen?' : 'Which actions would you like to show as separate buttons?' }}</legend>
            @foreach ($options as $option => $label)
                <div class="checkbox">
                    <label><input type="checkbox" name="sendactions[]" value="{{ $option }}" @if (in_array($option, $selected, true))checked="checked"@endif> {{ $label }}</label>
                </div>
            @endforeach
            <p class="help-block">{{ app()->getLocale() === 'de' ? 'Nur für dich · Für Antworten und Notizen in allen Postfächern. Ohne Auswahl bleibt die Standardansicht erhalten.' : 'Just for you · For replies and notes in all mailboxes. Leave all unchecked to keep the standard layout.' }}</p>
            <p class="help-block">{{ app()->getLocale() === 'de' ? 'Wenn Direktbuttons aktiviert sind, senden auch die Weiterleitungsoptionen im Sende-Dropdown sofort ab. Die Standardweiterleitung wird nicht geändert.' : 'When direct buttons are enabled, redirect options in the send dropdown also send immediately. Your default redirect is not changed.' }}</p>
        </fieldset>
        <p class="alert alert-danger sendactions-error hidden" role="alert"></p>
        <div class="text-right">
            <button type="button" class="btn btn-link" data-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary sendactions-save">{{ __('Save') }}</button>
        </div>
    </div>
</li>
