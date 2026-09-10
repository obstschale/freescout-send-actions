<?php

namespace Modules\SendActions\Providers;

use App\MailboxUser;
use App\Option;
use Illuminate\Support\ServiceProvider;

class SendActionsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'sendactions');

        \Eventy::addFilter('javascripts', function ($scripts) {
            $scripts[] = \Module::getPublicPath('sendactions').'/js/module.js';
            return $scripts;
        });
        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath('sendactions').'/css/module.css';
            return $styles;
        });

        \Eventy::addAction('user.edit.before_photo', function ($user) {
            echo view('sendactions::profile', [
                'selected' => self::selected($user->id),
                'options' => self::options(),
            ])->render();
        });

        // The core profile controller has already authorized and validated this request.
        \Eventy::addFilter('user.save_profile', function ($user, $request) {
            if ($request->input('sendactions_present') === '1') {
                $selected = self::normalize($request->input('sendactions', []));
                Option::set('sendactions.user.'.$user->id, $selected);
            }
            return $user;
        }, 20, 2);

        \Eventy::addAction('conv_editor.editor_toolbar_prepend', function ($mailbox, $conversation) {
            // New conversations and chat mode have different redirect semantics.
            if (!$conversation->id || $conversation->isDraft() || $conversation->isInChatMode() || !auth()->check()) {
                return;
            }
            $selected = self::selected(auth()->id());
            if ($selected) {
                echo view('sendactions::buttons', [
                    'selected' => $selected,
                    'options' => self::options(),
                ])->render();
            }
        }, 20, 2);
    }

    public static function options()
    {
        return [
            MailboxUser::AFTER_SEND_STAY => __('Stay on the same page'),
            MailboxUser::AFTER_SEND_NEXT => __('Next active conversation'),
            MailboxUser::AFTER_SEND_FOLDER => __('Back to folder'),
        ];
    }

    public static function normalize($selected)
    {
        if (!is_array($selected)) {
            return [];
        }
        return array_values(array_filter(array_keys(self::options()), function ($option) use ($selected) {
            return in_array($option, $selected, true) || in_array((string) $option, $selected, true);
        }));
    }

    public static function selected($user_id)
    {
        return self::normalize(Option::get('sendactions.user.'.$user_id, []));
    }
}
