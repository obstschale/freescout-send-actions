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
        public function check() { return true; }
        public function id() { return 17; }
    });
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
        $conversation->$mode = false;
    }

    $options = $provider::options();
    $buttons = $views->make('sendactions::buttons', ['selected' => [1, 3], 'options' => $options])->render();
    check(substr_count($buttons, 'class="btn btn-primary sendactions-direct"'), 2, 'Render selected buttons only');
    check(strpos($buttons, 'data-after-send="2"'), false, 'Unselected option not rendered');
    $profile = $views->make('sendactions::profile', ['selected' => [1, 3], 'options' => $options])->render();
    check(substr_count($profile, 'checked="checked"'), 2, 'Profile reflects saved choices');
    $session = new Illuminate\Session\Store('test', new Illuminate\Session\NullSessionHandler);
    $session->flashInput(['sendactions_present' => '1']);
    $application['request']->setLaravelSession($session);
    $empty_profile = $views->make('sendactions::profile', ['selected' => [1, 3], 'options' => $options])->render();
    check(substr_count($empty_profile, 'checked="checked"'), 0, 'Validation return preserves unchecked choices');

    if (getenv('SENDACTIONS_PREVIEW')) {
        file_put_contents(getenv('SENDACTIONS_PREVIEW'), json_encode([
            'profile' => $profile,
            'buttons' => $views->make('sendactions::buttons', ['selected' => [1, 2, 3], 'options' => $options])->render(),
        ]));
    }
    echo "PASS: preferences, user isolation, opt-out, input normalization, Blade rendering and validation old input\n";
} finally {
    $filesystem->deleteDirectory($cache);
}
