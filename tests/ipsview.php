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

function bundle(Energiefluss $module): array
{
    $module->messages = [];
    $module->RequestAction('LoadAppVisualization', 'test-request');
    $data = '';
    $total = count($module->messages);
    check($total > 1, 'Expected a chunked transfer');
    foreach ($module->messages as $index => $raw) {
        $packet = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        check($packet['command'] === 'visualizationBundle' && $packet['requestId'] === 'test-request', 'Wrong packet routing');
        check($packet['index'] === $index && $packet['total'] === $total, 'Incomplete transfer');
        check(strlen($packet['chunk']) <= 24000, 'Packet exceeds size limit');
        $data .= $packet['chunk'];
    }
    $decoded = base64_decode($data, true);
    check($decoded !== false, 'Invalid base64');
    return json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
}

$first = new Energiefluss(12345);
$second = new Energiefluss(12346);
$first->Create();
$second->Create();

$data = bundle($first);
foreach (['lit-core.min.js', 'power-flow-card.js', 'sunsynk-power-flow-card.js'] as $asset) {
    check($data['assets'][$asset] === file_get_contents(dirname(__DIR__) . '/Energie/assets/vendor/' . $asset), 'Asset transfer changed source bytes');
}
check(str_contains($data['html'], 'window.__EF_INSTANCE_ID__="12345"'), 'Missing instance identity');
check($data['viewState'] === ['view' => 'flow', 'layout' => 'lite'], 'Wrong initial view');
echo "PASS: bounded SDK transfer, routing, complete assets and instance identity\n";

$first->RequestAction('SaveAppViewState', '{"view":"house","layout":"compact-wide"}');
$second->RequestAction('SaveAppViewState', ['view' => 'flow', 'layout' => 'full']);
$reopened = new Energiefluss(12345);
$reopened->Create();
check(bundle($reopened)['viewState'] === ['view' => 'house', 'layout' => 'compact-wide'], 'First instance lost its view');
check(bundle($second)['viewState'] === ['view' => 'flow', 'layout' => 'full'], 'Second instance inherited another view');

foreach (['{}', '{"view":"other","layout":"lite"}', '{"view":"house","layout":"other"}'] as $invalid) {
    try {
        $first->RequestAction('SaveAppViewState', $invalid);
        throw new RuntimeException('Invalid state accepted');
    } catch (InvalidArgumentException $expected) {}
}
check(bundle($first)['viewState'] === ['view' => 'house', 'layout' => 'compact-wide'], 'Invalid state overwrote the saved view');
echo "PASS: separate persistent views, reopened instances and invalid-state rejection\n";

foreach (['', '../asset', str_repeat('a', 65)] as $invalid) {
    try {
        $first->RequestAction('LoadAppVisualization', $invalid);
        throw new RuntimeException('Invalid request accepted');
    } catch (InvalidArgumentException $expected) {}
}
$first->sendSucceeds = false;
try {
    $first->RequestAction('LoadAppVisualization', 'test-failure');
    throw new LogicException('Failed send was ignored');
} catch (RuntimeException $expected) {}
echo "PASS: invalid request and failed-send handling\n";

$first->setProperty('WallboxName', '</script><script>throw new Error("injected")</script>');
$tile = $first->GetVisualizationTile();
check(strlen($tile) < 32768, 'Initial HTML embeds the large asset bundle');
check(!str_contains($tile, '<script>throw new Error("injected")'), 'Label escapes the script context');
check(str_contains($tile, 'LoadAppVisualization'), 'Missing SDK bootstrap');
check(!str_contains($first->GetConfigurationForm(), 'VisualizationBaseUrl'), 'Manual address field reintroduced');
echo "PASS: small initial tile, script-safe labels and no manual address setting\n";
