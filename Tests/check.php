<?php

// Run with: php Modules/SendActions/Tests/check.php
// Uses an in-memory database; never boots or writes to the installed application.
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
require_once __DIR__.'/../Providers/SendActionsServiceProvider.php';
class_alias(App\Misc\Helper::class, 'Helper');

class Eventy
{
    public static $actions = [];
    public static $filters = [];

    public static function addAction($name, $callback, $priority = 20, $arguments = 1)
    {
        self::$actions[$name] = $callback;
    }

    public static function addFilter($name, $callback, $priority = 20, $arguments = 1)
    {
        self::$filters[$name] = $callback;
    }
}

function check($actual, $expected, $message)
{
    if ($actual !== $expected) {
        throw new RuntimeException($message.': '.var_export($actual, true));
    }
}

$application = new Illuminate\Foundation\Application($root);
$application->instance('config', new Illuminate\Config\Repository([
    'view' => ['paths' => []], 'app' => ['locale' => 'de'],
]));
$application->instance('request', Illuminate\Http\Request::create('/'));
$loader = new Illuminate\Translation\FileLoader(new Illuminate\Filesystem\Filesystem, $root.'/resources/lang');
$application->instance('translator', new Illuminate\Translation\Translator($loader, 'de'));
$database = new Illuminate\Database\Capsule\Manager($application);
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$database->setAsGlobal();
$database->bootEloquent();
$database->schema()->create('options', function ($table) {
    $table->increments('id');
    $table->string('name')->unique();
    $table->text('value');
});

$cache = sys_get_temp_dir().'/sendactions-views-'.getmypid();
mkdir($cache);
$filesystem = new Illuminate\Filesystem\Filesystem;
$compiler = new Illuminate\View\Compilers\BladeCompiler($filesystem, $cache);
$engines = new Illuminate\View\Engines\EngineResolver;
$engines->register('blade', function () use ($compiler) {
    return new Illuminate\View\Engines\CompilerEngine($compiler);
});
$views = new Illuminate\View\Factory($engines, new Illuminate\View\FileViewFinder($filesystem, []), new Illuminate\Events\Dispatcher($application));
$views->setContainer($application);
$application->instance('view', $views);
$application->alias('view', Illuminate\Contracts\View\Factory::class);
$provider = new Modules\SendActions\Providers\SendActionsServiceProvider($application);
$provider->boot();

try {
    check($provider::normalize(['3', '1', '3', true, '2x', [], 9]), [1, 3], 'Strict selection, canonical order and deduplication');
    check($provider::normalize('1'), [], 'Reject scalar selection');
    check($provider::selected(17), [], 'Default is no shortcuts');
    $save = Eventy::$filters['user.save_profile'];
    $user = (object) ['id' => 17];
    $save($user, new Illuminate\Http\Request(['sendactions_present' => '1', 'sendactions' => ['3', '1']]));
    App\Option::$cache = [];
    check($provider::selected(17), [1, 3], 'Persist selection through real options storage');
    check($provider::selected(18), [], 'Another user remains unchanged');
    $save($user, new Illuminate\Http\Request([]));
    App\Option::$cache = [];
    check($provider::selected(17), [1, 3], 'Profile without module fields preserves selection');
    $save($user, new Illuminate\Http\Request(['sendactions_present' => '1']));
    App\Option::$cache = [];
    check($provider::selected(17), [], 'Unchecking all clears selection');

    $application->instance('auth', new class {
        public $authenticated = true;
        public function check() { return $this->authenticated; }
        public function id() { return 17; }
    });
    $ajax = Eventy::$filters['users.ajax.response_default'];
    $original_response = ['status' => 'error', 'msg' => 'Unknown action'];
    check($ajax($original_response, new Illuminate\Http\Request(['action' => 'other.module'])), $original_response, 'Unrelated AJAX actions pass through');
    $response = $ajax($original_response, new Illuminate\Http\Request([
        'action' => 'sendactions.save', 'user_id' => 18, 'sendactions' => ['3', '2', '3', '1x'],
    ]));
    check($response['status'], 'success', 'AJAX save succeeds');
    check($response['selected'], [2, 3], 'AJAX normalizes choices');
    check(substr_count($response['buttons'], 'sendactions-direct'), 2, 'AJAX returns updated buttons');
    check(strpos($response['buttons'], 'data-after-send="1"'), false, 'AJAX omits unselected button');
    App\Option::$cache = [];
    check($provider::selected(17), [2, 3], 'AJAX persists own preferences');
    check($provider::selected(18), [], 'AJAX ignores supplied user ID');
    $response = $ajax($original_response, new Illuminate\Http\Request(['action' => 'sendactions.save', 'sendactions' => '1']));
    check($response['status'], 'error', 'Malformed AJAX selection rejected');
    check($provider::selected(17), [2, 3], 'Malformed request preserves preferences');
    $application['auth']->authenticated = false;
    $response = $ajax($original_response, new Illuminate\Http\Request(['action' => 'sendactions.save']));
    check($response['status'], 'error', 'Unauthenticated save rejected');
    check($provider::selected(17), [2, 3], 'Unauthenticated request preserves preferences');
    $application['auth']->authenticated = true;
    $response = $ajax($original_response, new Illuminate\Http\Request(['action' => 'sendactions.save']));
    check($response['selected'], [], 'AJAX accepts all unchecked');
    check($response['buttons'], '', 'Opt-out removes all direct buttons');
    App\Option::$cache = [];
    check($provider::selected(17), [], 'AJAX opt-out persists');

    $conversation = new class {
        public $id = 42;
        public $draft = false;
        public $chat = false;
        public function isDraft() { return $this->draft; }
        public function isInChatMode() { return $this->chat; }
    };
    $toolbar = Eventy::$actions['conv_editor.editor_toolbar_prepend'];
    ob_start();
    $toolbar(null, $conversation);
    check(ob_get_clean(), '', 'No selection adds no toolbar markup');
    $dropdown = Eventy::$actions['conversation.append_send_dropdown'];
    ob_start();
    $dropdown($conversation, null, false);
    $dropdown_html = ob_get_clean();
    check(substr_count($dropdown_html, 'class="sendactions-configure"'), 1, 'Settings available even without selected buttons');
    check(substr_count($dropdown_html, 'checked="checked"'), 0, 'Unconfigured modal has no selection');
    check(strpos($dropdown_html, 'data-after-send'), false, 'Settings entry cannot invoke send handler');
    ob_start();
    $dropdown($conversation, null, true);
    check(ob_get_clean(), '', 'No settings in new-conversation dropdown');
    $conversation->id = null;
    ob_start();
    $dropdown($conversation, null, false);
    check(ob_get_clean(), '', 'No settings without existing conversation');
    $conversation->id = 42;
    $application['auth']->authenticated = false;
    ob_start();
    $dropdown($conversation, null, false);
    check(ob_get_clean(), '', 'No settings for unauthenticated users');
    $application['auth']->authenticated = true;
    $save($user, new Illuminate\Http\Request(['sendactions_present' => '1', 'sendactions' => ['3']]));
    App\Option::$cache = [];
    ob_start();
    $toolbar(null, $conversation);
    check(substr_count(ob_get_clean(), 'data-after-send="3"'), 1, 'Toolbar uses authenticated user preference');
    foreach (['draft', 'chat'] as $mode) {
        $conversation->$mode = true;
        ob_start();
        $toolbar(null, $conversation);
        check(ob_get_clean(), '', 'No shortcuts in '.$mode.' editor');
        ob_start();
        $dropdown($conversation, null, false);
        check(ob_get_clean(), '', 'No settings in '.$mode.' editor');
        $conversation->$mode = false;
    }

    $options = $provider::options();
    $buttons = $views->make('sendactions::buttons', ['selected' => [1, 3], 'options' => $options])->render();
    check(substr_count($buttons, 'class="btn btn-primary sendactions-direct"'), 2, 'Render selected buttons only');
    check(strpos($buttons, 'data-after-send="2"'), false, 'Unselected option not rendered');
    foreach ([1 => 'pushpin', 2 => 'arrow-right', 3 => 'folder-open'] as $option => $icon) {
        $button = $views->make('sendactions::buttons', ['selected' => [$option], 'options' => $options])->render();
        check(substr_count($button, 'class="glyphicon glyphicon-'.$icon.'" aria-hidden="true"'), 1, 'Action '.$option.' has its decorative icon');
    }
    $profile = $views->make('sendactions::profile', ['selected' => [1, 3], 'options' => $options])->render();
    check(substr_count($profile, 'checked="checked"'), 2, 'Profile reflects saved choices');
    $modal = $views->make('sendactions::dropdown', ['selected' => [2], 'options' => $options])->render();
    check(substr_count($modal, 'checked="checked"'), 1, 'Modal reflects saved selection');
    check((bool) preg_match('/value="2"\s+checked="checked"/', $modal), true, 'Modal checks the selected action, not just any action');
    check(strpos($modal, 'Sendebuttons anpassen') !== false, true, 'German modal entry');
    $application->setLocale('en');
    $english_modal = $views->make('sendactions::dropdown', ['selected' => [], 'options' => $provider::options()])->render();
    check(strpos($english_modal, 'Customize send buttons') !== false, true, 'English modal entry');
    $application->setLocale('de');
    $session = new Illuminate\Session\Store('test', new Illuminate\Session\NullSessionHandler);
    $session->flashInput(['sendactions_present' => '1']);
    $application['request']->setLaravelSession($session);
    $empty_profile = $views->make('sendactions::profile', ['selected' => [1, 3], 'options' => $options])->render();
    check(substr_count($empty_profile, 'checked="checked"'), 0, 'Validation return preserves unchecked choices');

    if (getenv('SENDACTIONS_PREVIEW')) {
        file_put_contents(getenv('SENDACTIONS_PREVIEW'), json_encode([
            'profile' => $profile,
            'dropdown' => $dropdown_html,
            'buttons' => $views->make('sendactions::buttons', ['selected' => [1, 2, 3], 'options' => $options])->render(),
        ]));
    }
    echo "PASS: preferences, AJAX authorization and isolation, opt-out, dropdown visibility, Blade rendering and validation old input\n";
} finally {
    $filesystem->deleteDirectory($cache);
}
