<?php

declare(strict_types=1);

require __DIR__ . '/ipsview-stub.php';

require dirname(__DIR__) . '/Energie/module.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function appState(Energiefluss $module): array
{
    return (new ReflectionMethod($module, 'GetAppViewState'))->invoke($module);
}

$first = new Energiefluss(12345);
$second = new Energiefluss(12346);
$first->Create();
$second->Create();
$tile = $first->GetVisualizationTile();
check(preg_match('/const packed = ("[^\r\n]+");/', $tile, $match) === 1, 'Compressed bundle not embedded');
$bundle = json_decode(gzdecode(base64_decode(json_decode($match[1], true, 512, JSON_THROW_ON_ERROR), true)), true, 512, JSON_THROW_ON_ERROR);
foreach (['lit-core.min.js', 'power-flow-card.js', 'sunsynk-power-flow-card.js'] as $asset) {
    check($bundle['assets'][$asset] === file_get_contents(dirname(__DIR__) . '/Energie/assets/vendor/' . $asset), 'Original card source changed during bundling');
}
check($first->removedHooks === ['energiefluss-assets-12345'], 'Old instance hook not removed');
check(!str_contains($bundle['html'], '/hook/'), 'Host still needs the WebHook');
check(!str_contains($tile, '192.168.'), 'Local IP search reintroduced');
check(!str_contains($tile, 'private-test-password'), 'WebServer credentials leaked into HTML');
check(!str_contains($tile, 'LoadAppVisualization'), 'Chunk loading was reintroduced');
check(strlen($tile) < 900000 && strlen(json_encode(['result' => $tile], JSON_THROW_ON_ERROR)) < 1048576, 'Tile exceeds the real output buffer limit');
echo 'PASS: complete compressed original assets, no WebHook or IP discovery, tile ' . strlen($tile) . " bytes below the 1048576-byte limit\n";

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
check(str_contains($tile, 'viewState:appMode ? data.viewState : null'), 'App state overrides browser preferences');
echo "PASS: separate persistent instance views, restored app state and independent browser preferences\n";

function currentHostState(Energiefluss $module): array
{
    $module->RequestAction('InitializeVisualization', 'test-request');
    $message = json_decode($module->messages[count($module->messages) - 1], true, 512, JSON_THROW_ON_ERROR);
    check($message['command'] === 'initializeVisualization' && $message['requestId'] === 'test-request', 'Response cannot be assigned to its requesting client');
    check($message['viewState'] === appState($module), 'Fresh saved instance view not sent');
    return $message['payload'];
}

$cachedTile = $first->GetVisualizationTile();
$first->setProperty('Producers', '[{"Name":"Ost","VariableID":1001,"MaxPower":5000},{"Name":"West","VariableID":1002,"MaxPower":5000}]');
$first->setProperty('Batteries', '[{"Name":"Speicher","VariableID":1003,"SoCVariableID":1004}]');
$testVariables = [1001 => 2398, 1002 => 1971, 1003 => 2457, 1004 => 46];
$snapshot = currentHostState($first);
check(array_column($snapshot['pvs'], 'value') === [2398, 1971], 'Current PV values missing from first host state');
check($snapshot['batteries'][0]['value'] === 2457 && $snapshot['batteries'][0]['soc'] === 46, 'Current battery state missing');
check(is_int($snapshot['stateVersion']) && $snapshot['stateVersion'] > 0, 'Missing state version');
$testVariables[1001] = 2400;
$updatedSnapshot = currentHostState($first);
check($updatedSnapshot['pvs'][0]['value'] === 2400, 'Host reused stale data');
check($updatedSnapshot['stateVersion'] > $snapshot['stateVersion'], 'Host snapshots cannot be ordered by freshness');
check($cachedTile === $first->GetVisualizationTile(), 'Tile HTML still contains a cached initial data snapshot');
$testVariables[1001] = $testVariables[1002] = $testVariables[1003] = 0;
$zeroSnapshot = currentHostState($first);
check(count($zeroSnapshot['pvs']) === 2 && count($zeroSnapshot['batteries']) === 1, 'Zero power hides configured devices');
check(array_column($zeroSnapshot['pvs'], 'value') === [0, 0], 'Legitimate zero values were discarded');
echo "PASS: fresh SDK data independent of cached tile HTML, ordered snapshots and legitimate zero readings\n";

$label = '</script><script>throw new Error("injected")</script>';
$first->setProperty('Producers', json_encode([['Name' => $label, 'VariableID' => 1001]], JSON_THROW_ON_ERROR));
check(currentHostState($first)['pvs'][0]['name'] === $label, 'Escaping changed the configured label');
$tile = $first->GetVisualizationTile();
check(!str_contains($tile, '<script>throw new Error("injected")'), 'Label enters the cached tile script context');
check(!str_contains($first->GetConfigurationForm(), 'VisualizationBaseUrl'), 'Manual address field reintroduced');
foreach (['', '../bad', str_repeat('x', 97), ['not-a-string']] as $invalid) {
    try {
        $first->RequestAction('InitializeVisualization', $invalid);
        throw new RuntimeException('Invalid request accepted');
    } catch (InvalidArgumentException $expected) {}
}
$first->sendSucceeds = false;
try {
    $first->RequestAction('InitializeVisualization', 'failed-request');
    throw new LogicException('Failed SDK send ignored');
} catch (RuntimeException $expected) {}
echo "PASS: labels kept as data, validated request IDs, failed SDK send reported and no manual address setting\n";
