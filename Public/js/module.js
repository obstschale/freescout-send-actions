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

    $(document).on('click', '.sendactions-configure', function () {
        var trigger = $(this);
        showModal({
            title: trigger.text(),
            body: '<div>' + trigger.siblings('.sendactions-modal-body').html() + '</div>',
            no_footer: true,
            on_show: function (modal) {
                // Bootstrap traps focus; restore it to the menu toggle on close (WCAG 2.4.3).
                modal.on('shown.bs.modal', function () {
                    modal.find('input').first().trigger('focus');
                });
                modal.on('hidden.bs.modal', function () {
                    trigger.closest('.btn-group-send').find('.btn-send-menu').trigger('focus');
                });
                var saving = false;
                modal.on('hide.bs.modal', function (event) {
                    if (saving) {
                        event.preventDefault();
                    }
                });
                modal.on('click', '.sendactions-save', function () {
                    if (saving) {
                        return;
                    }
                    saving = true;
                    modal.attr('aria-busy', 'true');
                    modal.find('.sendactions-save').attr('aria-disabled', 'true');
                    modal.find('.sendactions-error').addClass('hidden');
                    var selected = modal.find('input:checked').map(function () {
                        return $(this).val();
                    }).get();
                    function showError(message) {
                        saving = false;
                        modal.removeAttr('aria-busy');
                        modal.find('.sendactions-save').removeAttr('aria-disabled');
                        modal.find('.sendactions-error').text(message).removeClass('hidden');
                    }
                    fsAjax({action: 'sendactions.save', sendactions: selected}, laroute.route('users.ajax'), function (response) {
                        if (response.status !== 'success') {
                            showError(response.msg || Lang.get('messages.ajax_error'));
                            return;
                        }
                        // Update both the live editor and its source toolbar without touching the draft or after_send.
                        $('.sendactions-modal-body input').each(function () {
                            var checkbox = $(this);
                            var checked = response.selected.indexOf(Number(checkbox.val())) !== -1;
                            checkbox.prop('checked', checked);
                            if (checked) {
                                checkbox.attr('checked', 'checked');
                            } else {
                                checkbox.removeAttr('checked');
                            }
                        });
                        $('.sendactions-configure').closest('.btn-group-send').each(function () {
                            var sendGroup = $(this);
                            var toolbar = sendGroup.parent();
                            if (toolbar.hasClass('sendactions-actions')) {
                                toolbar = toolbar.closest('.note-statusbar');
                            }
                            toolbar.find('.sendactions-buttons').remove();
                            toolbar.find('.sendactions-fields, .sendactions-actions').each(function () {
                                $(this).children().unwrap();
                            });
                            toolbar.find('.sendactions-layout').children().unwrap();
                            sendGroup.before(response.buttons);
                        });
                        positionButtons();
                        saving = false;
                        modal.modal('hide');
                    }, true, function () {
                        showError(Lang.get('messages.ajax_error'));
                    });
                });
            }
        });
    });

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
