<?php

declare(strict_types=1);

require __DIR__ . '/ipsview-stub.php';
require dirname(__DIR__) . '/Energie/module.php';

$module = new Energiefluss((int) ($argv[3] ?? 12345));
$module->Create();
if (isset($argv[4])) {
    $module->RequestAction('SaveAppViewState', $argv[4]);
}
$mode = $argv[1] ?? 'tile';
if ($mode === 'tile') {
    echo $module->GetVisualizationTile();
    exit;
}
$testVariables = [201 => -42, 202 => 2398, 203 => 1971, 204 => 2457, 205 => 46, 206 => 7102, 207 => 6237, 208 => 467, 209 => 7];
if ($mode === 'zero') {
    foreach ($testVariables as $id => $value) {
        if ($id !== 205) {
            $testVariables[$id] = 0;
        }
    }
}
$module->setProperty('L1', 201);
$module->setProperty('Producers', json_encode([
    ['Name' => $mode === 'label' ? '</script><script>window.injected=true</script>$&$$' : 'Ost', 'VariableID' => 202, 'MaxPower' => 5000],
    ['Name' => 'West', 'VariableID' => 203, 'MaxPower' => 5000],
], JSON_THROW_ON_ERROR));
$module->setProperty('Batteries', '[{"Name":"Speicher","VariableID":204,"SoCVariableID":205,"CapacityKWh":10}]');
$module->setProperty('Inverters', '[{"Name":"WR","PowerVariableID":206}]');
$module->setProperty('Groups', '[{"Name":"openWB","VariableID":207,"Icon":"charging-station","IsAux":true,"IsWallbox":true},{"Name":"Tumbler","VariableID":208,"Icon":"dryer"},{"Name":"Drucker","VariableID":209,"Icon":"print"}]');
if ($mode === 'fresh-tile') {
    echo $module->GetVisualizationTile();
    exit;
}
echo json_encode((new ReflectionMethod($module, 'BuildPayload'))->invoke($module), JSON_THROW_ON_ERROR);
