(function () {
    function positionButtons() {
        $('.note-statusbar .sendactions-buttons').each(function () {
            var shortcuts = $(this);
            if (shortcuts.parent().hasClass('sendactions-actions')) {
                return;
            }
            var toolbar = shortcuts.closest('.note-statusbar');
            var sendGroup = toolbar.find('.btn-group-send').first();
            var fields = toolbar.children('.editor-btm-text, select[name="status"], .note-bottom-div, select[name="user_id"]');
            fields.wrapAll('<div class="sendactions-fields"></div>');
            var actions = $('<div class="sendactions-actions"></div>').insertBefore(sendGroup);
            actions.append(shortcuts, sendGroup);
            fields.parent().add(actions).wrapAll('<div class="sendactions-layout"></div>');
        });
    }

    fsAddAction('conv_editor_init', positionButtons);
    $(document).ready(positionButtons);

    $(document).on('click', '.note-statusbar .sendactions-direct, .note-statusbar .dropdown-after-send a[data-after-send]', function (event) {
        var shortcut = $(this);
        var toolbar = shortcut.closest('.note-statusbar');
        // Without shortcuts (or while forwarding), keep the core dropdown behavior.
        if (!toolbar.find('.sendactions-direct:visible').length) {
            return;
        }
        // Core also clicks dropdown links programmatically when saving a new default.
        // Updating that setting must never send a message.
        if (!shortcut.hasClass('sendactions-direct') && !event.originalEvent) {
            return;
        }
        event.preventDefault();
        if (fs_processing_send_reply || upload_in_progress) {
            return;
        }

        var redirect = shortcut.attr('data-after-send');
        // Set before invoking core: draft autosave may retry using the first core button.
        $('[name="after_send"]').val(redirect);
        $('.dropdown-after-send li').removeClass('active');
        $('.dropdown-after-send a[data-after-send="' + redirect + '"]').parent().addClass('active');

        var selector = isNote() ? '.btn-add-note-text' : '.btn-send-text';
        toolbar.find(selector + '.btn-reply-submit').first().trigger('click');
    });
})();
