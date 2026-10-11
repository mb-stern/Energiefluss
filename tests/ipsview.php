<?php

declare(strict_types=1);

class IPSModuleStrict
{
    public array $messages = [];
    public bool $sendSucceeds = true;
    private array $properties = [];
    private static array $attributes = [];

    public function __construct(public int $InstanceID) {}
    public function Create(): void {}
    protected function RegisterHook(string $hook): void {}
    protected function SetVisualizationType(int $type): void {}
    protected function RegisterPropertyString(string $name, string $value): void { $this->properties[$name] = $value; }
    protected function RegisterPropertyInteger(string $name, int $value): void { $this->properties[$name] = $value; }
    protected function RegisterPropertyBoolean(string $name, bool $value): void { $this->properties[$name] = $value; }
    protected function ReadPropertyString(string $name): string { return $this->properties[$name]; }
    protected function ReadPropertyInteger(string $name): int { return $this->properties[$name]; }
    protected function ReadPropertyBoolean(string $name): bool { return $this->properties[$name]; }
    protected function RegisterAttributeString(string $name, string $value): void { self::$attributes[$this->InstanceID][$name] ??= $value; }
    protected function ReadAttributeString(string $name): string { return self::$attributes[$this->InstanceID][$name]; }
    protected function WriteAttributeString(string $name, string $value): void { self::$attributes[$this->InstanceID][$name] = $value; }
    protected function UpdateVisualizationValue(mixed $value): bool { $this->messages[] = $value; return $this->sendSucceeds; }
    protected function LogMessage(string $message, int $level): void { throw new RuntimeException($message); }
    public function setProperty(string $name, mixed $value): void { $this->properties[$name] = $value; }
}

function IPS_VariableExists(int $id): bool { return false; }
function IPS_ObjectExists(int $id): bool { return false; }

require dirname(__DIR__) . '/Energie/module.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$network = [
    ['IP' => '127.0.0.1'], ['IP' => '169.254.1.2'], ['IP' => '0.0.0.0'],
    ['IP' => '224.1.2.3'], ['IP' => '192.168.10.20'], ['IP' => '192.168.10.20'],
    ['IP' => '10.1.2.3'], ['IP' => '<script>bad</script>'],
];
$core = [
    11 => ['module' => 'WebServer', 'config' => ['Active' => true, 'Port' => 8443, 'Server' => '0.0.0.0', 'EnableSSL' => true, 'AuthPassword' => 'private-test-password']],
    12 => ['module' => 'WebServer', 'config' => ['Active' => false, 'Port' => 8080, 'Server' => '0.0.0.0']],
    13 => ['module' => 'Other', 'config' => ['Active' => true, 'Port' => 9000, 'Server' => '0.0.0.0']],
    14 => ['module' => 'WebServer', 'config' => ['Active' => true, 'Port' => 8081, 'Server' => '10.1.2.3', 'EnableSSL' => false]],
    15 => ['module' => 'WebServer', 'config' => ['Active' => true, 'Port' => 0, 'Server' => '0.0.0.0']],
];
function Sys_GetNetworkInfo(): array { return $GLOBALS['network']; }
function IPS_GetInstanceListByModuleType(int $type): array { return array_keys($GLOBALS['core']); }
function IPS_GetInstance(int $id): array { return ['ModuleInfo' => ['ModuleName' => $GLOBALS['core'][$id]['module']]]; }
function IPS_GetConfiguration(int $id): string { return json_encode($GLOBALS['core'][$id]['config'], JSON_THROW_ON_ERROR); }

function appState(Energiefluss $module): array
{
    return (new ReflectionMethod($module, 'GetAppViewState'))->invoke($module);
}

$first = new Energiefluss(12345);
$second = new Energiefluss(12346);
$first->Create();
$second->Create();
$origins = (new ReflectionMethod($first, 'GetVisualizationServerOrigins'))->invoke($first);
check($origins === [
    'http://192.168.10.20:3777', 'http://10.1.2.3:3777',
    'https://192.168.10.20:8443', 'https://10.1.2.3:8443', 'http://10.1.2.3:8081',
], 'Wrong server addresses, port, scheme, duplicates or adapter filtering');
$tile = $first->GetVisualizationTile();
check(str_contains($tile, 'http:\/\/192.168.10.20:3777') || str_contains($tile, 'http://192.168.10.20:3777'), 'Server addresses are not passed to the tile');
check(!str_contains($tile, 'private-test-password'), 'WebServer credentials leaked into HTML');
check(!str_contains($tile, 'LoadAppVisualization'), 'Chunk loading was reintroduced');
echo "PASS: actual server addresses and active WebServer ports, no credentials or chunk transfer\n";

check(appState($first) === ['view' => 'flow', 'layout' => 'lite'], 'Wrong initial view');
$first->RequestAction('SaveAppViewState', '{"view":"house","layout":"compact-wide"}');
$second->RequestAction('SaveAppViewState', ['view' => 'flow', 'layout' => 'full']);
$reopened = new Energiefluss(12345);
$reopened->Create();
check(appState($reopened) === ['view' => 'house', 'layout' => 'compact-wide'], 'First instance lost its view');
check(appState($second) === ['view' => 'flow', 'layout' => 'full'], 'Second instance inherited another view');
foreach (['{}', '{"view":"other","layout":"lite"}', '{"view":"house","layout":"other"}'] as $invalid) {
    try {
        $first->RequestAction('SaveAppViewState', $invalid);
        throw new RuntimeException('Invalid state accepted');
    } catch (InvalidArgumentException $expected) {}
}
check(appState($first) === ['view' => 'house', 'layout' => 'compact-wide'], 'Invalid state overwrote the saved view');
$appHost = (new ReflectionMethod($first, 'GetVisualizationHostHtml'))->invoke($first, true);
$browserHost = (new ReflectionMethod($first, 'GetVisualizationHostHtml'))->invoke($first);
check(str_contains($appHost, 'window.__EF_APP_VIEW_STATE__={"view":"house","layout":"compact-wide"}'), 'Saved instance state not embedded in app host');
check(str_contains($browserHost, 'window.__EF_APP_VIEW_STATE__=null'), 'App state overrides browser preferences');
echo "PASS: separate persistent instance views, restored app state and independent browser preferences\n";

$first->setProperty('WallboxName', '</script><script>throw new Error("injected")</script>');
$tile = $first->GetVisualizationTile();
check(strlen($tile) < 32768, 'Initial HTML embeds a large asset bundle');
check(!str_contains($tile, '<script>throw new Error("injected")'), 'Label escapes the script context');
check(!str_contains($first->GetConfigurationForm(), 'VisualizationBaseUrl'), 'Manual address field reintroduced');
echo "PASS: small initial tile, script-safe labels and no manual address setting\n";
