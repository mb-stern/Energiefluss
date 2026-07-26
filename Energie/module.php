<?php

/*
 * Zweite Visualisierungsansicht integriert die Open-Source-Karte:
 * LordGuenni/power-flow-card
 * https://github.com/LordGuenni/power-flow-card
 *
 * Autor: Florian Stamer
 * Lizenz: MIT (laut package.json des Projekts)
 *
 * Die Home-Assistant-Datenanbindung wird hier nicht verwendet.
 * Stattdessen erzeugt das IP-Symcon-Modul ein kompatibles State-Objekt
 * aus seinem bestehenden BuildPayload().
 * power-flow-card.js und Lit liegen versioniert im Modulbaum unter
 * assets/vendor/ und werden von ApplyChanges() lediglich in den
 * Web-Pfad /user/Energiefluss/vendor/ veröffentlicht.
 */

declare(strict_types=1);

class Energiefluss extends IPSModuleStrict
{
    private const CONFIG_MAP = [
        'Kp'               => 'SF_Kp',
        'Ki'               => 'SF_Ki',
        'Kd'               => 'SF_Kd',
        'TargetImport'     => 'SF_TargetImport',
        'ReserveHours'     => 'SF_ReserveHours',
        'FreeSoc'          => 'SF_FreeSocThreshold',
        'LowSocOut'        => 'SF_LowSocOutput',
        'LowSocThreshold'  => 'SF_LowSocThreshold',
        'MinSocShutdown'   => 'SF_MinSocShutdown',
        'MorningStart'     => 'SF_MorningStart',
        'MorningEnd'       => 'SF_MorningEnd',
    ];

    public function Create(): void
    {
        parent::Create();

        // Legacy-Eigenschaften für bestehende Instanzen.
        $this->RegisterPropertyInteger('SolarFlowPV', 0);
        $this->RegisterPropertyString('PV1Name', 'PV1');
        $this->RegisterPropertyInteger('PV1Energy', 0);
        $this->RegisterPropertyInteger('HoymilesPV', 0);
        $this->RegisterPropertyString('PV2Name', 'PV2');
        $this->RegisterPropertyInteger('PV2Energy', 0);
        $this->RegisterPropertyInteger('BatteryOut', 0);
        $this->RegisterPropertyInteger('BatterySoC', 0);

        // Dynamische Anlagen.
        $this->RegisterPropertyString('Producers', '[]');
        $this->RegisterPropertyString('Batteries', '[]');

        // Netz.
        $this->RegisterPropertyInteger('L1', 0);
        $this->RegisterPropertyInteger('L2', 0); // Legacy
        $this->RegisterPropertyInteger('L3', 0); // Legacy
        $this->RegisterPropertyInteger('GridExportPower', 0);
        $this->RegisterPropertyBoolean('InvertGridPower', false);
        $this->RegisterPropertyInteger('GridImportEnergy', 0);
        $this->RegisterPropertyInteger('GridExportEnergy', 0);

        // Wallbox.
        $this->RegisterPropertyString('WallboxName', 'Wallbox');
        $this->RegisterPropertyInteger('WallboxPower', 0);
        $this->RegisterPropertyInteger('WallboxEnergy', 0);

        // Weitere Darstellung.
        $this->RegisterPropertyInteger('SettingsCategory', 0);
        $this->RegisterPropertyString('Groups', '[]');
        $this->RegisterPropertyInteger('DayProduction', 0);
        $this->RegisterPropertyInteger('WeekProduction', 0);
        $this->RegisterPropertyInteger('DayGridImport', 0);
        $this->RegisterPropertyInteger('WeekGridImport', 0);

        // flow = klassische Energieflussansicht, house = Hausansicht.
        $this->RegisterPropertyString('DisplayMode', 'flow');

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        try {
            $this->EnsureVisualizationAssets();

            foreach ($this->GetMessageList() as $senderID => $messages) {
                foreach ($messages as $message) {
                    if ($message === VM_UPDATE) {
                        $this->UnregisterMessage($senderID, VM_UPDATE);
                    }
                }
            }

            foreach ($this->GetReferenceList() as $referenceID) {
                $this->UnregisterReference($referenceID);
            }

            foreach ($this->CollectVariableIDs() as $id) {
                if ($id > 0 && IPS_VariableExists($id)) {
                    $this->RegisterMessage($id, VM_UPDATE);
                    $this->RegisterReference($id);
                }
            }

            if (IPS_GetKernelRunlevel() === KR_READY) {
                $this->PushState();
            }
        } catch (Throwable $e) {
            $this->LogMessage('ApplyChanges: ' . $e->getMessage(), KL_ERROR);
        }
    }

    public function GetConfigurationForm(): string
    {
        $form = [
            'elements' => [
                [
                    'type'    => 'Select',
                    'name'    => 'DisplayMode',
                    'caption' => 'Darstellung',
                    'options' => [
                        ['caption' => 'Energiefluss', 'value' => 'flow'],
                        ['caption' => 'Hausansicht', 'value' => 'house'],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'PV, Netz, Batterie & Wallbox',
                    'items'   => [
                        [
                            'type'     => 'List',
                            'name'     => 'Producers',
                            'caption'  => 'PV-Anlagen',
                            'rowCount' => 3,
                            'add'      => true,
                            'delete'   => true,
                            'columns'  => [
                                [
                                    'caption' => 'Name',
                                    'name'    => 'Name',
                                    'width'   => '200px',
                                    'add'     => '',
                                    'edit'    => ['type' => 'ValidationTextBox'],
                                ],
                                [
                                    'caption' => 'Leistung',
                                    'name'    => 'VariableID',
                                    'width'   => '320px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Energie (optional)',
                                    'name'    => 'EnergyVariableID',
                                    'width'   => '320px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                            ],
                        ],
                        [
                            'type'     => 'List',
                            'name'     => 'Batteries',
                            'caption'  => 'Batterien',
                            'rowCount' => 3,
                            'add'      => true,
                            'delete'   => true,
                            'columns'  => [
                                [
                                    'caption' => 'Name',
                                    'name'    => 'Name',
                                    'width'   => '180px',
                                    'add'     => '',
                                    'edit'    => ['type' => 'ValidationTextBox'],
                                ],
                                [
                                    'caption' => 'Leistung',
                                    'name'    => 'VariableID',
                                    'width'   => '280px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'SOC',
                                    'name'    => 'SoCVariableID',
                                    'width'   => '240px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Energie (optional)',
                                    'name'    => 'EnergyVariableID',
                                    'width'   => '260px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Fluss umkehren',
                                    'name'    => 'InvertFlow',
                                    'width'   => '120px',
                                    'add'     => false,
                                    'edit'    => ['type' => 'CheckBox'],
                                ],
                            ],
                        ],
                        ['type' => 'Label', 'caption' => 'Netz'],
                        ['type' => 'SelectVariable', 'name' => 'L1', 'caption' => 'Netzleistung (W)'],
                        ['type' => 'CheckBox', 'name' => 'InvertGridPower', 'caption' => 'Vorzeichen der Netzleistung umkehren'],
                        ['type' => 'SelectVariable', 'name' => 'GridExportPower', 'caption' => 'Rücklieferung Leistung (W, optional)'],
                        ['type' => 'SelectVariable', 'name' => 'GridImportEnergy', 'caption' => 'Netzbezug gesamt (kWh)'],
                        ['type' => 'SelectVariable', 'name' => 'GridExportEnergy', 'caption' => 'Rücklieferung / Einspeisung gesamt (kWh)'],

                        ['type' => 'Label', 'caption' => 'Wallbox'],
                        ['type' => 'ValidationTextBox', 'name' => 'WallboxName', 'caption' => 'Name'],
                        ['type' => 'SelectVariable', 'name' => 'WallboxPower', 'caption' => 'Ladeleistung (W)'],
                        ['type' => 'SelectVariable', 'name' => 'WallboxEnergy', 'caption' => 'Ladeenergie (optional)'],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Verbrauchergruppen (optional, paarweise)',
                    'items'   => [
                        [
                            'type'     => 'List',
                            'name'     => 'Groups',
                            'caption'  => 'Verbrauchergruppen',
                            'rowCount' => 10,
                            'add'      => true,
                            'delete'   => true,
                            'columns'  => [
                                [
                                    'caption' => 'Name',
                                    'name'    => 'Name',
                                    'width'   => '180px',
                                    'add'     => '',
                                    'edit'    => ['type' => 'ValidationTextBox'],
                                ],
                                [
                                    'caption' => 'Leistungs-Variable',
                                    'name'    => 'VariableID',
                                    'width'   => 'auto',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Tagesverbrauch (optional)',
                                    'name'    => 'DailyVariableID',
                                    'width'   => 'auto',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Icon',
                                    'name'    => 'Icon',
                                    'width'   => '140px',
                                    'add'     => 'plug',
                                    'edit'    => ['type' => 'SelectIcon'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Statistik (optional)',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Zählervariablen für die Statistik-Anzeige rechts. Die Einheit kommt aus dem Variablenprofil.',
                        ],
                        ['type' => 'SelectVariable', 'name' => 'DayProduction', 'caption' => 'Tagesproduktion gesamt'],
                        ['type' => 'SelectVariable', 'name' => 'WeekProduction', 'caption' => 'Wochenproduktion gesamt'],
                        ['type' => 'SelectVariable', 'name' => 'DayGridImport', 'caption' => 'Tagesverbrauch Netzbezug'],
                        ['type' => 'SelectVariable', 'name' => 'WeekGridImport', 'caption' => 'Wochenverbrauch Netzbezug gesamt'],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'SolarFlow-Regelung (optional)',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Kategorie \'SolarFlow Einstellungen\' auswählen, die das PID-Skript anlegt.',
                        ],
                        [
                            'type'    => 'SelectCategory',
                            'name'    => 'SettingsCategory',
                            'caption' => 'Kategorie \'SolarFlow Einstellungen\'',
                        ],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type'    => 'Button',
                    'caption' => 'HTML neu laden',
                    'onClick' => 'ENERGIE_ReloadHtml($id);',
                ],
            ],
            'status' => [],
        ];

        return json_encode(
            $form,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        try {
            if ($Message === VM_UPDATE && IPS_GetKernelRunlevel() === KR_READY) {
                $this->PushState();
            }
        } catch (Throwable $e) {
            $this->LogMessage('MessageSink: ' . $e->getMessage(), KL_ERROR);
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident === 'SetDisplayMode') {
            $mode = (string) $Value;

            if (!in_array($mode, ['flow', 'house'], true)) {
                return;
            }

            if ($mode !== $this->ReadPropertyString('DisplayMode')) {
                IPS_SetProperty($this->InstanceID, 'DisplayMode', $mode);
                IPS_ApplyChanges($this->InstanceID);
            }

            return;
        }

        if (str_starts_with($Ident, 'Cfg')) {
            $field = substr($Ident, 3);
            if (!array_key_exists($field, self::CONFIG_MAP)) {
                return;
            }

            $catID = $this->ReadPropertyInteger('SettingsCategory');
            if ($catID <= 0 || !IPS_ObjectExists($catID)) {
                return;
            }

            $varID = @IPS_GetObjectIDByIdent(self::CONFIG_MAP[$field], $catID);
            if ($varID === false || !IPS_VariableExists($varID)) {
                return;
            }

            $type = IPS_GetVariable($varID)['VariableType'];
            if ($type === VARIABLETYPE_STRING) {
                SetValue($varID, (string) $Value);
            } elseif ($type === VARIABLETYPE_INTEGER) {
                SetValue($varID, (int) round((float) $Value));
            } else {
                SetValue($varID, (float) $Value);
            }

            $this->PushState();
        }
    }

    public function ReloadHtml(): void
    {
        $this->UpdateVisualizationValue(
            json_encode(
                ['command' => 'reloadHtml'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    public function GetVisualizationTile(): string
    {
        try {
            $payload = json_encode(
                $this->BuildPayload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            return $this->GetVisualizationHtml($this->ReadPropertyString('DisplayMode'))
                . '<script>handleMessage(' . $payload . ');</script>';
        } catch (Throwable $e) {
            return '<div style="padding:1em">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    private function GetVisualizationHtml(string $displayMode): string
    {
        $showHouse = $displayMode === 'house';
        $flowDisplay = $showHouse ? 'none' : 'block';
        $houseDisplay = $showHouse ? 'block' : 'none';

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    body {
        margin: 0;
        font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: transparent;
        position: relative;
    }
    :root {
        --w-bg: #f6f6f3;
        --w-surface: #ffffff;
        --w-text: #20201e;
        --w-text2: #6c6c66;
        --w-line: #d3d3ce;
        --w-border: #e6e6e1;
    }
    :root[data-theme="dark"] {
        --w-bg: #1b1b19;
        --w-surface: #272725;
        --w-text: #f1f1ee;
        --w-text2: #9a9a93;
        --w-line: #3b3b38;
        --w-border: #343431;
    }

    html, body { width: 100%; height: 100%; overflow: hidden; }

    #eflow {
        width: 100%;
        height: 100vh;
        box-sizing: border-box;
        border-radius: 12px;
        padding: 10px;
        background: var(--w-bg);
        overflow: hidden;
    }
    #scale-host {
        width: 100%;
        height: 100%;
        overflow: hidden;
        position: relative;
    }
    #scale-root {
        width: 540px;
        height: 640px;
        position: absolute;
        left: 0;
        top: 0;
        transform-origin: 0 0;
    }
    #wrap {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        width: 540px;
        height: 640px;
    }
    #fit {
        width: 540px;
        height: 640px;
        flex: 0 0 540px;
        overflow: hidden;
        position: relative;
    }

    #view-switch {
        position: absolute;
        top: 6px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        display: inline-flex;
        gap: 2px;
        padding: 2px;
        border-radius: 6px;
        background: var(--w-surface);
        border: 1px solid var(--w-border);
        box-shadow: 0 1px 6px rgba(0,0,0,.12);
        pointer-events: auto;
    }

    .view-switch-btn {
        display: block;
        box-sizing: border-box;
        min-width: 0;
        border: 0;
        border-radius: 4px;
        padding: 4px 7px;
        margin: 0;
        background: transparent;
        color: var(--w-text2);
        font: inherit;
        font-size: 11px;
        line-height: 1.15;
        cursor: pointer;
        pointer-events: auto;
        touch-action: manipulation;
    }

    .view-switch-btn.active {
        background: var(--w-text);
        color: var(--w-surface);
    }

    .view-switch-btn * {
        pointer-events: none;
    }

    /* Klassische Ansicht */
    #stage {
        position: relative;
        width: 1080px;
        height: 640px;
        display: __FLOW_DISPLAY__;
    }
    #svg { position: absolute; inset: 0; z-index: 1; }
    #svg #lines line, #svg #lines path { stroke: var(--w-line); }
    .node {
        position: absolute;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        background: var(--w-surface);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 2;
        text-align: center;
    }
    .node .val { color: var(--w-text); font-weight: 500; }
    .node .sub { color: var(--w-text2); }
    .lbl {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        color: var(--w-text2);
        white-space: nowrap;
    }
    .lbl.top { bottom: 100%; margin-bottom: 8px; }
    .lbl.bot { top: 100%; margin-top: 8px; }

    /* Rechte Statistik/Regelung */
    #cfg {
        flex: 0 0 250px;
        width: 250px;
        border-left: 0.5px solid var(--w-border);
        padding-left: 14px;
        box-sizing: border-box;
    }
    #cfgsec { margin-top: 12px; }
    #statsec + #cfgsec[style=""] {
        border-top: 0.5px solid var(--w-border);
        padding-top: 10px;
    }
    .cfg-h {
        font-size: 14px;
        font-weight: 500;
        color: var(--w-text);
        margin-bottom: 10px;
    }
    .cfg-live {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 6px;
    }
    .lv {
        background: var(--w-surface);
        border: 0.5px solid var(--w-border);
        border-radius: 8px;
        padding: 7px 10px;
        display: flex;
        justify-content: space-between;
        font-size: 13px;
    }
    .lv span { color: var(--w-text2); }
    .lv b { color: var(--w-text); font-weight: 500; }
    .cfg-sub {
        font-size: 12px;
        font-weight: 500;
        color: var(--w-text2);
        margin: 10px 0 2px;
    }
    .cfg-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2px;
    }
    .fld {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        padding: 3px 0;
    }
    .fld > span { color: var(--w-text2); }
    .ed {
        color: var(--w-text);
        font-weight: 500;
        border: 0.5px solid var(--w-border);
        background: var(--w-surface);
        border-radius: 6px;
        padding: 4px 8px;
        width: 92px;
        text-align: right;
        font-size: 13px;
        font-family: inherit;
    }

    /* Hausansicht – LordGuenni/power-flow-card */
    #house-stage {
        position: relative;
        width: 900px;
        height: 640px;
        display: __HOUSE_DISPLAY__;
        overflow: hidden;
        box-sizing: border-box;
        border-radius: 18px;
        border: 1px solid #263443;
        background: #111827;
    }

    #pfc-host {
        position: absolute;
        left: 0;
        top: 0;
        width: 900px;
        height: 640px;
        overflow: hidden;
    }

    #pfc-host power-flow-card {
        display: block;
        width: 100%;
        height: 100%;
        --primary-text-color: #f3f4f6;
        --secondary-text-color: #9ca3af;
        --card-background-color: transparent;
        --ha-card-background: transparent;
        --energy-solar-color: #EFA020;
        --energy-grid-consumption-color: #ff4d43;
        --energy-grid-return-color: #6fd32f;
        --energy-battery-charge-color: #6fd32f;
        --energy-battery-discharge-color: #3ca0ff;
        --energy-car-color: #22d3d0;
    }

    #pfc-loading,
    #pfc-error {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 13px;
        z-index: 5;
        pointer-events: none;
    }

    #pfc-error {
        display: none;
        color: #ff6b6b;
        padding: 30px;
        text-align: center;
        box-sizing: border-box;
    }

    #pfc-info-layer {
        position: absolute;
        inset: 0;
        z-index: 7;
        pointer-events: none;
    }

    .pfc-info {
        position: absolute;
        min-width: 118px;
        max-width: 190px;
        padding: 7px 9px;
        box-sizing: border-box;
        border-radius: 9px;
        background: rgba(10, 16, 24, .88);
        border: 1px solid rgba(255,255,255,.12);
        box-shadow: 0 5px 18px rgba(0,0,0,.24);
        color: #f3f4f6;
        line-height: 1.25;
        backdrop-filter: blur(2px);
    }

    .pfc-info .title {
        color: #aeb8c3;
        font-size: 10px;
        font-weight: 650;
        margin-bottom: 2px;
    }

    .pfc-info .main {
        font-size: 17px;
        font-weight: 700;
        white-space: nowrap;
    }

    .pfc-info .sub {
        color: #98a5b3;
        font-size: 9px;
        margin-top: 2px;
        line-height: 1.3;
    }

    #pfc-info-solar {
        left: 60%;
        top: 4%;
        border-color: rgba(239,160,32,.36);
    }

    #pfc-info-home {
        right: 7%;
        top: 28%;
        border-color: rgba(77,159,255,.36);
    }

    #pfc-info-battery {
        left: 55%;
        bottom: 17%;
        transform: translateX(-50%);
        border-color: rgba(60,160,255,.36);
    }

    #pfc-info-wallbox {
        left: 19%;
        top: 39%;
        bottom: auto;
        border-color: rgba(34,211,208,.36);
    }

    /* Netz sitzt unten direkt bei den beiden Import-/Export-Leitungen. */
    #pfc-info-grid {
        right: 4%;
        bottom: 4%;
        min-width: 165px;
        border-color: rgba(255,255,255,.16);
    }

    #pfc-grid-import {
        color: #ff4d43;
    }

    #pfc-grid-export {
        color: #6fd32f;
    }

    #pfc-battery-main.discharge {
        color: #3ca0ff;
    }

    #pfc-battery-main.charge {
        color: #6fd32f;
    }

    #pfc-solar-main {
        color: #EFA020;
    }

    #pfc-home-main {
        color: #4d9fff;
    }

    #pfc-wallbox-main {
        color: #22d3d0;
    }


</style>
</head>
<body>
<script src="/icons.js"></script>
<script type="module"
        src="/user/Energiefluss/vendor/power-flow-card.js">
</script>

<div id="eflow">
    <div id="scale-host">
        <div id="scale-root">
            <div id="wrap">
                <div id="fit">

                    <div id="view-switch">
                        <button id="view-flow" class="view-switch-btn" type="button">Energiefluss</button>
                        <button id="view-house" class="view-switch-btn" type="button">Haus</button>
                    </div>

                    <!-- Klassische Energieflussansicht -->
                    <div id="stage">
                        <svg id="svg" width="1080" height="640" viewBox="0 0 1080 640" aria-hidden="true">
                            <g id="lines" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></g>
                            <g id="ring"></g>
                            <g id="dots"></g>
                        </svg>
                    </div>

                    <!-- Hausansicht: originale LordGuenni Power Flow Card -->
                    <div id="house-stage">
                        <div id="pfc-host"></div>
                        <div id="pfc-loading">Power Flow Card wird geladen …</div>
                        <div id="pfc-error"></div>

                        <div id="pfc-info-layer">
                            <div id="pfc-info-solar" class="pfc-info">
                                <div id="pfc-solar-title" class="title">PV</div>
                                <div id="pfc-solar-main" class="main">0 W</div>
                                <div id="pfc-solar-sub" class="sub"></div>
                            </div>

                            <div id="pfc-info-home" class="pfc-info">
                                <div class="title">Hausverbrauch</div>
                                <div id="pfc-home-main" class="main">0 W</div>
                            </div>

                            <div id="pfc-info-battery" class="pfc-info">
                                <div id="pfc-battery-title" class="title">Batterie</div>
                                <div id="pfc-battery-main" class="main discharge">0 W</div>
                                <div id="pfc-battery-sub" class="sub"></div>
                            </div>

                            <div id="pfc-info-wallbox" class="pfc-info">
                                <div id="pfc-wallbox-title" class="title">Wallbox</div>
                                <div id="pfc-wallbox-main" class="main">0 W</div>
                                <div id="pfc-wallbox-sub" class="sub"></div>
                            </div>

                            <div id="pfc-info-grid" class="pfc-info">
                                <div class="title">Netz</div>
                                <div id="pfc-grid-import" class="main">→ 0 W</div>
                                <div id="pfc-grid-export" class="main">← 0 W</div>
                                <div id="pfc-grid-sub" class="sub"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="cfg" style="display:none">
                    <div id="statsec" style="display:none">
                        <div class="cfg-h"><i class="fa-solid fa-chart-simple" style="margin-right:6px"></i>Statistik</div>
                        <div class="cfg-live" id="stats-body"></div>
                    </div>
                    <div id="cfgsec" style="display:none">
                        <div class="cfg-h"><i class="fa-solid fa-sliders" style="margin-right:6px"></i>SolarFlow-Regelung</div>
                        <div class="cfg-live">
                            <div class="lv"><span>Aktueller Ausgang</span><b id="cfg-out">&ndash;</b></div>
                            <div class="lv"><span>Regelziel</span><b>Nulleinspeisung</b></div>
                        </div>
                        <div id="cfg-body"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function detectTheme() {
        let probe = getComputedStyle(document.documentElement).getPropertyValue('--content-color').trim();
        if (!probe) probe = getComputedStyle(document.body).color;

        let dark = null;
        const m = probe && probe.match(/rgba?\((\d+)[,\s]+(\d+)[,\s]+(\d+)/);
        if (m) {
            const lum = (0.299 * m[1] + 0.587 * m[2] + 0.114 * m[3]) / 255;
            dark = lum > 0.5;
        } else if (probe && probe[0] === '#' && probe.length >= 7) {
            const r = parseInt(probe.substr(1, 2), 16);
            const g = parseInt(probe.substr(3, 2), 16);
            const b = parseInt(probe.substr(5, 2), 16);
            dark = (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.5;
        }

        if (dark === null) {
            dark = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
        }

        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    }

    detectTheme();
    window.addEventListener('load', detectTheme);
    setInterval(detectTheme, 2000);

    const AC = {
        solar: '#EFA020',
        grid: '#3B82C4',
        room: '#2FA98F',
        batt: '#4F9A5B',
        import: '#ff4d43',
        export: '#6fd32f',
        discharge: '#3ca0ff',
        charge: '#6fd32f',
        wallbox: '#22d3d0',
        home: '#4d9fff'
    };

    const NSc = 'http://www.w3.org/2000/svg';
    const RR = 34;
    const COL0 = 530;
    const COLW = 120;

    // ---------- klassische Ansicht ----------
    const MAIN = {
        netz: { x: 110, y: 350, r: 46, ic: 'bolt', icc: AC.grid, lab: 'Netz', lp: 'bot' },
        haus: { x: 360, y: 350, r: 52, ic: 'house', icc: 'var(--w-text)', lab: 'Haus', lp: 'bot', ring: true }
    };

    const stage = document.getElementById('stage');
    const linesG = document.getElementById('lines');
    const dotsG = document.getElementById('dots');
    const ringG = document.getElementById('ring');

    const E = {
        'netz-haus': { d: 'M156,350 L306,350', col: AC.grid }
    };

    const lineEl = {};
    const dotEl = {};
    let edgeState = {};
    let edgePhase = {};

    function fmt(w) {
        return Math.round(w || 0).toLocaleString('de-DE') + ' W';
    }

    function addNode(id, n, cls) {
        const el = document.createElement('div');
        el.className = 'node' + (cls ? ' ' + cls : '');
        el.id = 'n-' + id;

        const border = n.ring ? 'transparent' : n.icc;
        const isz = n.r < 40 ? 18 : 22;

        el.style.cssText =
            `left:${n.x}px;top:${n.y}px;width:${n.r * 2}px;height:${n.r * 2}px;border:3px solid ${border};`;

        el.innerHTML =
            `<div class="lbl ${n.lp}" style="font-size:${n.r < 40 ? 12 : 14}px">${n.lab}</div>` +
            `<i class="fa-solid fa-${n.ic}" style="font-size:${isz}px;color:${n.icc}"></i>` +
            `<div class="body" id="body-${id}" style="font-size:${n.r < 40 ? 12 : 15}px"></div>`;

        stage.appendChild(el);
    }

    for (const id in MAIN) {
        addNode(id, MAIN[id]);
    }

    function addEdge(k, d, col) {
        const p = document.createElementNS(NSc, 'path');
        p.setAttribute('d', d);
        p.style.stroke = col;
        p.style.fill = 'none';
        p.style.strokeWidth = '2.5';
        linesG.appendChild(p);
        lineEl[k] = p;

        dotEl[k] = [0, 1].map(() => {
            const c = document.createElementNS(NSc, 'circle');
            c.setAttribute('r', 5);
            c.setAttribute('fill', col);
            c.style.display = 'none';
            dotsG.appendChild(c);
            return c;
        });
    }

    for (const k in E) {
        addEdge(k, E[k].d, E[k].col);
    }

    function sourceX(i) {
        return 360 - (i * 120);
    }

    function pvPos(i) {
        return { x: sourceX(i), y: 105 };
    }

    function batteryPos(i) {
        return { x: sourceX(i), y: 548 };
    }

    function gpos(i) {
        const col = Math.floor(i / 2);
        const top = i % 2 === 0;
        return {
            x: COL0 + col * COLW,
            y: top ? 205 : 495,
            lp: top ? 'top' : 'bot'
        };
    }

    function clearDynamicSources() {
        document.querySelectorAll('.pv-node, .battery-node').forEach(e => e.remove());

        Object.keys(lineEl)
            .filter(k => k.startsWith('pv') || k.startsWith('bat'))
            .forEach(k => {
                lineEl[k].remove();
                dotEl[k].forEach(d => d.remove());
                delete lineEl[k];
                delete dotEl[k];
            });
    }

    function buildPVs(list) {
        list.forEach((pv, i) => {
            const p = pvPos(i);

            addNode(
                'pv' + i,
                {
                    x: p.x,
                    y: p.y,
                    r: 44,
                    ic: 'solar-panel',
                    icc: AC.solar,
                    lab: pv.name || ('PV ' + (i + 1)),
                    lp: 'top'
                },
                'pv-node'
            );

            document.getElementById('body-pv' + i).innerHTML =
                `<div class="val">${fmt(pv.value)}</div>` +
                (pv.energy
                    ? `<div class="sub" style="font-size:10px;line-height:1.25;">${pv.energy}</div>`
                    : '');

            addEdge(
                'pv' + i,
                `M${p.x},149 L${p.x},250 L360,250 L360,298`,
                AC.solar
            );
        });
    }

    function buildBatteries(list) {
        list.forEach((bat, i) => {
            const p = batteryPos(i);
            const batColor = (bat.value || 0) >= 0 ? AC.discharge : AC.charge;

            addNode(
                'bat' + i,
                {
                    x: p.x,
                    y: p.y,
                    r: 42,
                    ic: 'battery-half',
                    icc: batColor,
                    lab: bat.name || ('Batterie ' + (i + 1)),
                    lp: 'bot',
                    ring: true
                },
                'battery-node'
            );

            document.getElementById('body-bat' + i).innerHTML =
                `<div class="sub" style="font-size:11px">${Math.round(bat.soc || 0)}%</div>` +
                `<div class="val" style="color:${batColor}">${fmt(Math.abs(bat.value || 0))}</div>` +
                (bat.energy
                    ? `<div class="sub" style="font-size:10px;line-height:1.25;">${bat.energy}</div>`
                    : '');

            addEdge(
                'bat' + i,
                `M${p.x},506 L${p.x},455 L360,455 L360,402`,
                batColor
            );
        });
    }

    function buildGroups(list) {
        document.querySelectorAll('.grp-node').forEach(e => e.remove());

        Object.keys(lineEl)
            .filter(k => k.startsWith('grp'))
            .forEach(k => {
                lineEl[k].remove();
                dotEl[k].forEach(d => d.remove());
                delete lineEl[k];
                delete dotEl[k];
            });

        list.forEach((g, i) => {
            const p = gpos(i);

            addNode(
                'r' + i,
                {
                    x: p.x,
                    y: p.y,
                    r: RR,
                    ic: g.icon || 'plug',
                    icc: AC.room,
                    lab: g.name || ('Gruppe ' + (i + 1)),
                    lp: p.lp
                },
                'grp-node'
            );

            let inner = `<div class="val">${fmt(g.value)}</div>`;
            if (g.daily) {
                inner += `<div class="sub" style="font-size:10px;line-height:1.25;">${g.daily}</div>`;
            }
            document.getElementById('body-r' + i).innerHTML = inner;

            const endY = p.lp === 'top' ? p.y + RR : p.y - RR;
            addEdge('grp' + i, `M412,350 L${p.x},350 L${p.x},${endY}`, AC.room);
        });
    }

    function arc(cx, cy, r, col, frac, off) {
        const C = 2 * Math.PI * r;
        const seg = Math.max(frac * C, 0);
        const c = document.createElementNS(NSc, 'circle');

        c.setAttribute('cx', cx);
        c.setAttribute('cy', cy);
        c.setAttribute('r', r);
        c.setAttribute('fill', 'none');
        c.setAttribute('stroke', col);
        c.setAttribute('stroke-width', 5);
        c.setAttribute('stroke-dasharray', `${Math.max(seg - 4, 0)} ${C - Math.max(seg - 4, 0)}`);
        c.setAttribute('stroke-dashoffset', -off * C);
        c.setAttribute('transform', `rotate(-90 ${cx} ${cy})`);
        ringG.appendChild(c);
    }

    function track(cx, cy, r) {
        const c = document.createElementNS(NSc, 'circle');
        c.setAttribute('cx', cx);
        c.setAttribute('cy', cy);
        c.setAttribute('r', r);
        c.setAttribute('fill', 'none');
        c.setAttribute('stroke', 'var(--w-line)');
        c.setAttribute('stroke-width', 5);
        ringG.appendChild(c);
    }

    function updateRings(segs, batteries) {
        ringG.innerHTML = '';

        track(360, 350, 60);
        const tot = segs.reduce((a, s) => a + s[1], 0) || 1;
        let acc = 0;

        segs.forEach(([col, v]) => {
            if (v > 0) {
                arc(360, 350, 60, col, v / tot, acc / tot);
                acc += v;
            }
        });

        batteries.forEach((bat, i) => {
            const p = batteryPos(i);
            const batColor = (bat.value || 0) >= 0 ? AC.discharge : AC.charge;
            track(p.x, p.y, 48);
            arc(p.x, p.y, 48, batColor, Math.max(0, Math.min(100, bat.soc || 0)) / 100, 0);
        });
    }

    // ---------- Hausansicht: Adapter für LordGuenni/power-flow-card ----------
    const houseStage = document.getElementById('house-stage');
    const pfcHost = document.getElementById('pfc-host');
    const pfcLoading = document.getElementById('pfc-loading');
    const pfcError = document.getElementById('pfc-error');

    let pfcCard = null;
    let pfcPendingData = null;
    let pfcInitPromise = null;

    function pfcState(value, unit = 'W') {
        const numeric = Number(value);
        const rounded = Number.isFinite(numeric)
            ? (unit === 'W' ? Math.round(numeric) : numeric)
            : 0;

        return {
            state: String(rounded),
            attributes: {
                unit_of_measurement: unit
            }
        };
    }

    function pfcDurationForPower(power) {
        const p = Math.max(100, Math.min(10000, Math.abs(power || 0)));
        const ratio = (p - 100) / (10000 - 100);

        // Wie im Originalprojekt: hohe Leistung = kürzere Animationsdauer.
        return 5 - (ratio * 4);
    }

    function createPfcConfig() {
        return {
            name: '',
            threshold: 1,

            dynamic_speed_enabled: true,
            min_flow_speed: 5,
            max_flow_speed: 1,
            min_power_threshold: 100,
            max_power_threshold: 10000,

            solar_line_color: AC.solar,
            grid_import_line_color: AC.import,
            grid_export_line_color: AC.export,
            battery_charge_line_color: AC.charge,
            battery_discharge_line_color: AC.discharge,
            ev_line_color: AC.wallbox,

            entities: {
                solar_power: 'sensor.symcon_solar',
                grid_import_power: 'sensor.symcon_grid_import',
                grid_export_power: 'sensor.symcon_grid_export',
                ev_charge_power: 'sensor.symcon_ev',
                battery_charge_power: 'sensor.symcon_battery_charge',
                battery_discharge_power: 'sensor.symcon_battery_discharge'
            },

            solar_descriptor_enabled: false,
            solar_descriptor_label: 'PV gesamt',
            solar_descriptor_entity: 'sensor.symcon_solar',

            grid_descriptor_enabled: false,
            grid_descriptor_label: 'Netz',
            grid_descriptor_entity: 'sensor.symcon_grid',

            battery_descriptor_enabled: false,
            battery_descriptor_label: 'Batterie',
            battery_descriptor_entity: 'sensor.symcon_battery_soc',

            ev_descriptor_enabled: false,
            ev_descriptor_label: 'Wallbox',
            ev_descriptor_entity: 'sensor.symcon_ev',

            home_descriptor_enabled: false,
            home_descriptor_label: 'Haus',
            home_descriptor_entity: 'sensor.symcon_home'
        };
    }

    function installPfcShadowOverrides(card) {
        if (!card || !card.shadowRoot) {
            return;
        }

        if (!card.shadowRoot.getElementById('symcon-pfc-overrides')) {
            const style = document.createElement('style');
            style.id = 'symcon-pfc-overrides';
            style.textContent = `
                :host {
                    width: 100% !important;
                    height: 100% !important;
                }

                ha-card {
                    height: 100% !important;
                    background: transparent !important;
                    box-shadow: none !important;
                    border: 0 !important;
                    overflow: hidden !important;
                }

                ha-card .card-header {
                    display: none !important;
                }

                #svg-overlay {
                    width: 100% !important;
                    height: 100% !important;
                    min-height: 0 !important;
                    padding: 4px !important;
                    box-sizing: border-box !important;
                }

                #svg-overlay > div {
                    width: 100% !important;
                    height: 100% !important;
                }

                #svg-overlay svg {
                    width: 100% !important;
                    height: 100% !important;
                    max-width: none !important;
                    max-height: none !important;
                }

                #svg-container-bg svg {
                    opacity: .78 !important;
                }

                .descriptor-value {
                    font-size: 25px !important;
                    font-weight: 700 !important;
                }

                .descriptor-label {
                    font-size: 19px !important;
                }

            `;
            card.shadowRoot.appendChild(style);
        }
    }


    function applyPfcOptionalLayers(d, batteries, wallbox) {
        if (!pfcCard || !pfcCard.shadowRoot) {
            return;
        }

        const hasBattery = batteries.length > 0;
        const hasWallbox = !!d.hasWallbox;

        const batteryContainer =
            pfcCard.shadowRoot.getElementById('svg-container-battery');

        // Die Wallbox benutzt jetzt den früheren Grid-Import-Container "primary".
        const wallboxContainer =
            pfcCard.shadowRoot.getElementById('svg-container-primary');

        // Der alte EV-Pfad wird nicht mehr verwendet.
        const oldEvContainer =
            pfcCard.shadowRoot.getElementById('svg-container-ev');

        if (batteryContainer) {
            batteryContainer.style.display = hasBattery ? '' : 'none';
        }

        if (wallboxContainer) {
            wallboxContainer.style.display = hasWallbox ? '' : 'none';
        }

        if (oldEvContainer) {
            oldEvContainer.style.display = 'none';
        }

        // Die Descriptor-Gruppen befinden sich im SVG-Overlay.
        const batteryDescriptor =
            pfcCard.shadowRoot.querySelector('.descriptor-battery');
        const evDescriptor =
            pfcCard.shadowRoot.querySelector('.descriptor-ev');

        if (batteryDescriptor) {
            batteryDescriptor.style.display = hasBattery ? '' : 'none';
        }

        if (evDescriptor) {
            evDescriptor.style.display = hasWallbox ? '' : 'none';
        }
    }


    async function ensurePowerFlowCard() {
        if (pfcCard) {
            return pfcCard;
        }

        if (pfcInitPromise) {
            return pfcInitPromise;
        }

        pfcInitPromise = (async () => {
            try {
                // Das externe Modul registriert das Custom Element.
                await customElements.whenDefined('power-flow-card');

                const card = document.createElement('power-flow-card');

                // Wichtig: Konfiguration VOR dem Einhängen setzen,
                // weil render() im Upstream-Projekt this.config verwendet.
                card.setConfig(createPfcConfig());

                // Initiale Dummy-Zustände.
                card.hass = {
                    states: {
                        'sensor.symcon_solar': pfcState(0),
                        'sensor.symcon_grid_import': pfcState(0),
                        'sensor.symcon_grid_export': pfcState(0),
                        'sensor.symcon_ev': pfcState(0),
                        'sensor.symcon_battery_charge': pfcState(0),
                        'sensor.symcon_battery_discharge': pfcState(0),
                        'sensor.symcon_grid': pfcState(0),
                        'sensor.symcon_battery_soc': pfcState(0, '%'),
                        'sensor.symcon_home': pfcState(0)
                    }
                };

                pfcHost.replaceChildren(card);
                pfcCard = card;

                // Lit benötigt einen Render-Zyklus, bevor Shadow-DOM vorhanden ist.
                await new Promise(resolve => requestAnimationFrame(() =>
                    requestAnimationFrame(resolve)
                ));

                installPfcShadowOverrides(card);

                if (pfcLoading) {
                    pfcLoading.style.display = 'none';
                }

                if (pfcPendingData) {
                    const pending = pfcPendingData;
                    pfcPendingData = null;
                    updatePowerFlowCard(...pending);
                }

                return card;
            } catch (err) {
                console.error('Power Flow Card konnte nicht initialisiert werden:', err);

                if (pfcLoading) {
                    pfcLoading.style.display = 'none';
                }
                if (pfcError) {
                    pfcError.style.display = 'flex';
                    pfcError.textContent =
                        'Power Flow Card konnte nicht geladen werden. ' +
                        'Prüfe assets/vendor/ im Modulbaum und das Symcon-Log.';
                }

                throw err;
            }
        })();

        return pfcInitPromise;
    }

    function updatePfcInfoCards(d, grid, haus, pvs, batteries, wallbox) {
        const pvTotal = pvs.reduce((sum, pv) => sum + (pv.value || 0), 0);
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);

        // PV: bei mehreren Anlagen Namen und Energie darunter auflisten.
        const pvTitle = document.getElementById('pfc-solar-title');
        const pvMain = document.getElementById('pfc-solar-main');
        const pvSub = document.getElementById('pfc-solar-sub');

        if (pvTitle) {
            pvTitle.textContent = pvs.length === 1
                ? (pvs[0].name || 'PV')
                : 'PV gesamt';
        }
        if (pvMain) {
            pvMain.textContent = fmt(pvTotal);
        }
        if (pvSub) {
            pvSub.innerHTML = pvs.map((pv, i) => {
                const name = pv.name || ('PV ' + (i + 1));
                const energy = pv.energy ? ` · ${pv.energy}` : '';
                return `${name}: ${fmt(pv.value || 0)}${energy}`;
            }).join('<br>');
        }

        // Haus
        const homeMain = document.getElementById('pfc-home-main');
        if (homeMain) {
            homeMain.textContent = fmt(haus);
        }

        // Batterie: blau = Entladung Richtung Haus, grün = Laden Richtung Batterie.
        const batteryInfo = document.getElementById('pfc-info-battery');
        const batteryTitle = document.getElementById('pfc-battery-title');
        const batteryMain = document.getElementById('pfc-battery-main');
        const batterySub = document.getElementById('pfc-battery-sub');

        if (batteryInfo) {
            batteryInfo.style.display = batteries.length ? '' : 'none';
        }

        if (batteries.length) {
            const mainBat = batteries[0];
            const discharge = batteryTotal >= 0;

            if (batteryTitle) {
                batteryTitle.textContent = batteries.length === 1
                    ? (mainBat.name || 'Batterie')
                    : 'Batterien';
            }

            if (batteryMain) {
                batteryMain.textContent = fmt(Math.abs(batteryTotal));
                batteryMain.classList.toggle('discharge', discharge);
                batteryMain.classList.toggle('charge', !discharge);
            }

            if (batterySub) {
                batterySub.innerHTML = batteries.map((bat, i) => {
                    const name = bat.name || ('Batterie ' + (i + 1));
                    const mode = (bat.value || 0) >= 0 ? 'Entladen' : 'Laden';
                    const energy = bat.energy ? ` · ${bat.energy}` : '';
                    return `${name}: ${Math.round(bat.soc || 0)} % · ${mode}${energy}`;
                }).join('<br>');
            }
        }

        // Wallbox
        const wallboxInfo = document.getElementById('pfc-info-wallbox');
        if (wallboxInfo) {
            wallboxInfo.style.display = d.hasWallbox ? '' : 'none';
        }

        if (d.hasWallbox) {
            const wallboxTitle = document.getElementById('pfc-wallbox-title');
            const wallboxMain = document.getElementById('pfc-wallbox-main');
            const wallboxSub = document.getElementById('pfc-wallbox-sub');

            if (wallboxTitle) {
                wallboxTitle.textContent = wallbox.name || 'Wallbox';
            }
            if (wallboxMain) {
                wallboxMain.textContent = fmt(wallbox.value || 0);
            }
            if (wallboxSub) {
                wallboxSub.textContent = wallbox.energy || '';
            }
        }

        // Netz unten: beide Richtungen immer separat darstellen.
        const gridImport = Math.max(grid, 0);
        const gridExport = Math.max(-grid, 0);

        const gridImportEl = document.getElementById('pfc-grid-import');
        const gridExportEl = document.getElementById('pfc-grid-export');
        const gridSub = document.getElementById('pfc-grid-sub');

        if (gridImportEl) {
            gridImportEl.textContent = `→ ${fmt(gridImport)}`;
        }
        if (gridExportEl) {
            gridExportEl.textContent = `← ${fmt(gridExport)}`;
        }

        if (gridSub) {
            const energy = [];
            if (d.gridImportEnergy) {
                energy.push('Bezug ' + d.gridImportEnergy);
            }
            if (d.gridExportEnergy) {
                energy.push('Einspeisung ' + d.gridExportEnergy);
            }
            gridSub.innerHTML = energy.join('<br>');
        }
    }

    function updatePowerFlowCard(d, grid, haus, pvs, batteries, wallbox) {
        if (!pfcCard) {
            updatePfcInfoCards(d, grid, haus, pvs, batteries, wallbox);
            pfcPendingData = [d, grid, haus, pvs, batteries, wallbox];
            ensurePowerFlowCard().catch(() => {});
            return;
        }

        const pvTotal = pvs.reduce((sum, pv) => sum + (pv.value || 0), 0);
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);

        // Modulkonvention:
        // Batterie positiv = Entladen, negativ = Laden.
        const batteryCharge = Math.max(-batteryTotal, 0);
        const batteryDischarge = Math.max(batteryTotal, 0);

        // Netz positiv = Bezug, negativ = Einspeisung.
        const gridImport = Math.max(grid, 0);
        const gridExport = Math.max(-grid, 0);

        const mainSoc = batteries.length
            ? Math.max(0, Math.min(100, batteries[0].soc || 0))
            : 0;

        const states = {
            'sensor.symcon_solar': pfcState(pvTotal),
            'sensor.symcon_grid_import': pfcState(gridImport),
            'sensor.symcon_grid_export': pfcState(gridExport),
            'sensor.symcon_ev': pfcState(
                d.hasWallbox ? Math.max(wallbox.value || 0, 0) : 0
            ),
            'sensor.symcon_battery_charge': pfcState(batteryCharge),
            'sensor.symcon_battery_discharge': pfcState(batteryDischarge),

            // Deskriptoren:
            'sensor.symcon_grid': pfcState(Math.abs(grid)),
            'sensor.symcon_battery_soc': pfcState(mainSoc, '%'),
            'sensor.symcon_home': pfcState(haus)
        };

        pfcCard.hass = { states };

        updatePfcInfoCards(d, grid, haus, pvs, batteries, wallbox);

        installPfcShadowOverrides(pfcCard);
        applyPfcOptionalLayers(d, batteries, wallbox);

        // Upstream updateFlow() wird bereits vom hass-Setter ausgelöst.
        // Für den Fall eines noch laufenden Initialisierungszyklus nochmals
        // nach dem nächsten Frame anwenden.
        requestAnimationFrame(() => {
            if (pfcCard && pfcCard.isInitialized && typeof pfcCard.updateFlow === 'function') {
                pfcCard.updateFlow();
                applyPfcOptionalLayers(d, batteries, wallbox);
                    }
        });
    }

    function buildHouseView(d, grid, haus, pvs, batteries, wallbox) {
        updatePowerFlowCard(d, grid, haus, pvs, batteries, wallbox);
    }

    let currentDisplayMode = 'flow';

    function applyDisplayMode(mode) {
        currentDisplayMode = mode === 'house' ? 'house' : 'flow';
        const house = currentDisplayMode === 'house';

        if (stage) {
            stage.style.display = house ? 'none' : 'block';
        }

        if (houseStage) {
            houseStage.style.display = house ? 'block' : 'none';
        }

        const flowButton = document.getElementById('view-flow');
        const houseButton = document.getElementById('view-house');

        if (flowButton) {
            flowButton.classList.toggle('active', !house);
        }

        if (houseButton) {
            houseButton.classList.toggle('active', house);
        }
    }

    function switchDisplayMode(mode) {
        const normalized = mode === 'house' ? 'house' : 'flow';

        // Sofort sichtbar umschalten.
        applyDisplayMode(normalized);

        // Layout sofort passend machen.
        const groups = document.querySelectorAll('.grp-node').length;
        const pvs = document.querySelectorAll('.pv-node').length;
        const batteries = document.querySelectorAll('.battery-node').length;
        const cfgEl = document.getElementById('cfg');
        const showRightPanel = !!(cfgEl && cfgEl.style.display !== 'none');

        updateLayout(groups, pvs, batteries, showRightPanel, normalized);

        // Dieselbe DisplayMode-Property persistent setzen,
        // die auch das Konfigurationsformular verwendet.
        requestAction('SetDisplayMode', normalized);
    }

    // ---------- Regelung / Statistik ----------
    const CFG = [
        {
            sub: 'Sollwerte',
            fields: [
                { id: 'TargetImport', label: 'Ziel-Netzbezug', step: 1 },
                { id: 'ReserveHours', label: 'Reserve', step: 0.5 }
            ]
        },
        {
            sub: 'SOC-Schwellen',
            fields: [
                { id: 'FreeSoc', label: 'Free-SOC-Schwelle', step: 1 },
                { id: 'LowSocThreshold', label: 'Low-SOC-Schwelle', step: 1 },
                { id: 'LowSocOut', label: 'Low-SOC Fixausgabe', step: 1 },
                { id: 'MinSocShutdown', label: 'Min-SOC Abschaltung', step: 1 }
            ]
        },
        {
            sub: 'Morgenlogik',
            fields: [
                { id: 'MorningStart', label: 'Morgen Start', type: 'time' },
                { id: 'MorningEnd', label: 'Morgen Ende', type: 'time' }
            ]
        },
        {
            sub: 'PID-Regler',
            top: true,
            fields: [
                { id: 'Kp', label: 'Reaktionsstärke (Kp)', step: 0.01 },
                { id: 'Ki', label: 'Langzeit-Ausgleich (Ki)', step: 0.01 },
                { id: 'Kd', label: 'Dämpfung (Kd)', step: 0.01 }
            ]
        }
    ];

    function buildCfg() {
        let h = '';

        CFG.forEach(s => {
            h += `<div class="cfg-sub"${s.top ? ' style="margin-top:16px;border-top:0.5px solid var(--w-border);padding-top:12px"' : ''}>${s.sub}</div><div class="cfg-grid">`;

            s.fields.forEach(f => {
                h +=
                    `<div class="fld"><span>${f.label}</span><input class="ed" id="cfg-${f.id}" type="${f.type || 'number'}"${f.step ? ` step="${f.step}"` : ''} onchange="onCfg('${f.id}')"></div>`;
            });

            h += '</div>';
        });

        document.getElementById('cfg-body').innerHTML = h;
    }

    function onCfg(id) {
        const el = document.getElementById('cfg-' + id);

        if (el.type === 'time') {
            requestAction('Cfg' + id, el.value);
            return;
        }

        const v = parseFloat(el.value);
        if (isNaN(v)) {
            return;
        }

        requestAction('Cfg' + id, v);
    }

    buildCfg();

    // ---------- Layout ----------
    let layoutWidth = 540;

    function updateLayout(groupCount, pvCount, batteryCount, showRightPanel, mode = 'flow') {
        const fitEl = document.getElementById('fit');
        const wrapEl = document.getElementById('wrap');
        const rootEl = document.getElementById('scale-root');

        let graphWidth = mode === 'house' ? 900 : 540;

        if (mode !== 'house') {
            const columns = Math.ceil(groupCount / 2);

            if (columns > 0) {
                graphWidth = Math.max(graphWidth, 650 + ((columns - 1) * COLW));
            }

            graphWidth = Math.min(graphWidth, 1080);
        }

        const rightWidth = showRightPanel ? 264 : 0;
        layoutWidth = graphWidth + rightWidth;

        fitEl.style.width = graphWidth + 'px';
        fitEl.style.flexBasis = graphWidth + 'px';

        wrapEl.style.width = layoutWidth + 'px';
        rootEl.style.width = layoutWidth + 'px';

        wrapEl.style.gap = showRightPanel ? '14px' : '0px';

        fit();
    }

    // ---------- Zustand ----------
    function setState(d) {
        const grid = d.grid || 0;
        const imp = Math.max(grid, 0);

        const pvs = d.pvs || [];
        const batteries = d.batteries || [];
        const groups = d.groups || [];
        const wallbox = d.wallbox || { name: 'Wallbox', value: 0, energy: '' };

        const pvTotal = pvs.reduce((sum, pv) => sum + (pv.value || 0), 0);
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);

        // Netzbezug positiv, Rücklieferung negativ.
        const haus = Math.max(pvTotal + batteryTotal + grid, 0);

        // Klassische Ansicht.
        clearDynamicSources();
        buildPVs(pvs);
        buildBatteries(batteries);
        buildGroups(groups);

        const gridColor = grid >= 0 ? AC.import : AC.export;
        const gridNode = document.getElementById('n-netz');
        if (gridNode) {
            gridNode.style.borderColor = gridColor;
            const icon = gridNode.querySelector('i');
            if (icon) {
                icon.style.color = gridColor;
            }
        }

        if (lineEl['netz-haus']) {
            lineEl['netz-haus'].style.stroke = gridColor;
        }
        if (dotEl['netz-haus']) {
            dotEl['netz-haus'].forEach(dot => dot.setAttribute('fill', gridColor));
        }

        document.getElementById('body-netz').innerHTML =
            `<div class="val" style="color:${gridColor}">${fmt(Math.abs(grid))}</div>` +
            (d.gridImportEnergy
                ? `<div class="sub" style="font-size:10px;line-height:1.25;color:${AC.import}">&rarr; ${d.gridImportEnergy}</div>`
                : '') +
            (d.gridExportEnergy
                ? `<div class="sub" style="font-size:10px;line-height:1.25;color:${AC.export}">&larr; ${d.gridExportEnergy}</div>`
                : '');

        document.getElementById('body-haus').innerHTML =
            `<div class="val" style="font-size:17px">${fmt(haus)}</div>`;

        updateRings(
            [
                [AC.solar, Math.max(pvTotal, 0)],
                [AC.discharge, Math.max(batteryTotal, 0)],
                [AC.import, imp]
            ],
            batteries
        );

        edgeState = {
            'netz-haus': { w: Math.abs(grid), rev: grid < 0 }
        };

        pvs.forEach((pv, i) => {
            edgeState['pv' + i] = {
                w: Math.max(pv.value || 0, 0),
                rev: false
            };
        });

        batteries.forEach((bat, i) => {
            edgeState['bat' + i] = {
                w: Math.abs(bat.value || 0),
                rev: (bat.value || 0) < 0
            };
        });

        groups.forEach((g, i) => {
            edgeState['grp' + i] = { w: g.value || 0, rev: false };
        });

        for (const k in lineEl) {
            const on = edgeState[k] && edgeState[k].w > 0;
            if (dotEl[k]) {
                dotEl[k].forEach(dot => dot.style.display = on ? 'block' : 'none');
            }
        }

        // Hausansicht V2.
        buildHouseView(d, grid, haus, pvs, batteries, wallbox);
        applyDisplayMode(d.displayMode || 'flow');

        // Statistik.
        const stats = d.stats || [];
        document.getElementById('stats-body').innerHTML = stats.map(s =>
            '<div class="lv"><span>' + s.label + '</span><b>' + s.value + '</b></div>'
        ).join('');
        document.getElementById('statsec').style.display = stats.length ? '' : 'none';

        // Regelung.
        const hasCfg = !!(d.hasConfig && d.config);
        document.getElementById('cfgsec').style.display = hasCfg ? '' : 'none';

        if (hasCfg) {
            CFG.forEach(s => s.fields.forEach(f => {
                const el = document.getElementById('cfg-' + f.id);
                const cv = d.config[f.id];

                if (el && document.activeElement !== el && cv !== undefined && cv !== null) {
                    el.value = cv;
                }
            }));

            document.getElementById('cfg-out').textContent = fmt(batteryTotal);
        }

        const showRightPanel = !!(stats.length || hasCfg);
        document.getElementById('cfg').style.display = showRightPanel ? '' : 'none';

        updateLayout(
            groups.length,
            pvs.length,
            batteries.length,
            showRightPanel,
            (d.displayMode || 'flow') === 'house' ? 'house' : 'flow'
        );
    }

    function handleMessage(data) {
        const d = typeof data === 'string' ? JSON.parse(data) : data;

        if (d && d.command === 'reloadHtml') {
            window.location.reload();
            return;
        }

        setState(d);
    }

    const viewFlowButton = document.getElementById('view-flow');
    const viewHouseButton = document.getElementById('view-house');

    if (viewFlowButton) {
        viewFlowButton.addEventListener('click', function () {
            switchDisplayMode('flow');
        });
    }

    if (viewHouseButton) {
        viewHouseButton.addEventListener('click', function () {
            switchDisplayMode('house');
        });
    }

    // Wie im Sankey zusätzlich den Message-Kanal anbinden.
    window.addEventListener('message', function (event) {
        handleMessage(event.data);
    });

    // ---------- Animation ----------
    let last = performance.now();

    function powerSpeed(w) {
        const power = Math.max(0, Math.abs(w || 0));
        if (power <= 0) {
            return 0;
        }

        const speed = 0.040 + (Math.sqrt(power) * 0.00285);
        return Math.min(speed, 0.32);
    }

    function animateEdges(dt, stateMap, lineMap, dotMap, phaseMap) {
        for (const k in stateMap) {
            const st = stateMap[k];
            if (!st || st.w <= 0 || !lineMap[k]) {
                continue;
            }

            if (phaseMap[k] === undefined) {
                phaseMap[k] = 0;
            }

            phaseMap[k] = (phaseMap[k] + (dt * powerSpeed(st.w))) % 1;

            const path = lineMap[k];
            const len = path.getTotalLength();

            dotMap[k].forEach((dot, i) => {
                let t = (phaseMap[k] + i / dotMap[k].length) % 1;
                if (st.rev) {
                    t = 1 - t;
                }

                const pt = path.getPointAtLength(t * len);
                dot.setAttribute('cx', pt.x);
                dot.setAttribute('cy', pt.y);
            });
        }
    }

    function frame(now) {
        const dt = (now - last) / 1000;
        last = now;

        animateEdges(dt, edgeState, lineEl, dotEl, edgePhase);

        requestAnimationFrame(frame);
    }

    // ---------- Skalierung ----------
    function fit() {
        const host = document.getElementById('scale-host');
        const root = document.getElementById('scale-root');

        if (!host || !root) {
            return;
        }

        const baseWidth = layoutWidth;
        const baseHeight = 640;

        const availableWidth = host.clientWidth;
        const availableHeight = host.clientHeight;

        if (availableWidth <= 0 || availableHeight <= 0) {
            return;
        }

        const scaleX = availableWidth / baseWidth;
        const scaleY = availableHeight / baseHeight;
        const scale = Math.min(scaleX, scaleY);

        root.style.transform = `scale(${scale})`;

        const scaledWidth = baseWidth * scale;
        const scaledHeight = baseHeight * scale;

        root.style.left = `${Math.max(0, (availableWidth - scaledWidth) / 2)}px`;
        root.style.top = `${Math.max(0, (availableHeight - scaledHeight) / 2)}px`;
    }

    const scaleHost = document.getElementById('scale-host');
    if (scaleHost) {
        new ResizeObserver(fit).observe(scaleHost);
    }

    window.addEventListener('resize', fit);
    window.addEventListener('load', fit);

    fit();
    requestAnimationFrame(frame);
</script>
</body>
</html>
HTML;

        return str_replace(
            ['__FLOW_DISPLAY__', '__HOUSE_DISPLAY__'],
            [$flowDisplay, $houseDisplay],
            $html
        );
    }

    private function EnsureVisualizationAssets(): void
    {
        /*
         * Quelle der Visualisierungsdateien ist ausschließlich der Modulbaum:
         *
         *   assets/vendor/power-flow-card.js
         *   assets/vendor/lit-core.min.js
         *
         * Der /user/-Ordner ist nur die vom Symcon-Webserver erreichbare
         * Laufzeitkopie. Es findet keinerlei Download aus dem Internet statt.
         */
        $sourceDir = __DIR__
            . DIRECTORY_SEPARATOR
            . 'assets'
            . DIRECTORY_SEPARATOR
            . 'vendor';

        $targetDir = IPS_GetKernelDir()
            . 'user'
            . DIRECTORY_SEPARATOR
            . 'Energiefluss'
            . DIRECTORY_SEPARATOR
            . 'vendor';

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                $this->LogMessage(
                    'Hausansicht: Web-Verzeichnis konnte nicht erstellt werden: ' . $targetDir,
                    KL_ERROR
                );
                return;
            }
        }

        $assets = [
            'power-flow-card.js',
            'lit-core.min.js',
        ];

        foreach ($assets as $asset) {
            $source = $sourceDir . DIRECTORY_SEPARATOR . $asset;
            $target = $targetDir . DIRECTORY_SEPARATOR . $asset;

            if (!is_file($source)) {
                $this->LogMessage(
                    'Hausansicht: Datei fehlt im Modulbaum: ' . $source,
                    KL_ERROR
                );
                continue;
            }

            $copyRequired = !is_file($target);

            if (!$copyRequired) {
                $sourceSize = @filesize($source);
                $targetSize = @filesize($target);
                $sourceMTime = @filemtime($source);
                $targetMTime = @filemtime($target);

                $copyRequired =
                    $sourceSize !== $targetSize
                    || $sourceMTime === false
                    || $targetMTime === false
                    || $sourceMTime > $targetMTime;
            }

            if (!$copyRequired) {
                continue;
            }

            if (!@copy($source, $target)) {
                $this->LogMessage(
                    'Hausansicht: Datei konnte nicht veröffentlicht werden: ' . $asset,
                    KL_ERROR
                );
                continue;
            }

            $mtime = @filemtime($source);
            if ($mtime !== false) {
                @touch($target, $mtime);
            }
        }
    }


    private function PushState(): void
    {
        $this->UpdateVisualizationValue(
            json_encode(
                $this->BuildPayload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    private function ReadVar(string $property): float
    {
        $id = $this->ReadPropertyInteger($property);
        if ($id > 0 && IPS_VariableExists($id)) {
            return (float) GetValue($id);
        }

        return 0.0;
    }

    private function ReadVarFormatted(string $property): string
    {
        $id = $this->ReadPropertyInteger($property);
        if ($id > 0 && IPS_VariableExists($id)) {
            return GetValueFormatted($id);
        }

        return '';
    }

    private function CollectVariableIDs(): array
    {
        $ids = [];

        foreach ([
            'SolarFlowPV',
            'PV1Energy',
            'HoymilesPV',
            'PV2Energy',
            'BatteryOut',
            'BatterySoC',
            'L1',
            'L2',
            'L3',
            'GridExportPower',
            'GridImportEnergy',
            'GridExportEnergy',
            'WallboxPower',
            'WallboxEnergy',
            'DayProduction',
            'WeekProduction',
            'DayGridImport',
            'WeekGridImport',
        ] as $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $producers = json_decode($this->ReadPropertyString('Producers'), true);
        if (is_array($producers)) {
            foreach ($producers as $producer) {
                foreach (['VariableID', 'EnergyVariableID'] as $key) {
                    $variableID = (int) ($producer[$key] ?? 0);
                    if ($variableID > 0) {
                        $ids[] = $variableID;
                    }
                }
            }
        }

        $batteries = json_decode($this->ReadPropertyString('Batteries'), true);
        if (is_array($batteries)) {
            foreach ($batteries as $battery) {
                foreach (['VariableID', 'EnergyVariableID', 'SoCVariableID'] as $key) {
                    $variableID = (int) ($battery[$key] ?? 0);
                    if ($variableID > 0) {
                        $ids[] = $variableID;
                    }
                }
            }
        }

        $groups = json_decode($this->ReadPropertyString('Groups'), true);
        if (is_array($groups)) {
            foreach ($groups as $group) {
                $variableID = (int) ($group['VariableID'] ?? 0);
                if ($variableID > 0) {
                    $ids[] = $variableID;
                }

                $dailyVariableID = (int) ($group['DailyVariableID'] ?? 0);
                if ($dailyVariableID > 0) {
                    $ids[] = $dailyVariableID;
                }
            }
        }

        $catID = $this->ReadPropertyInteger('SettingsCategory');
        if ($catID > 0 && IPS_ObjectExists($catID)) {
            foreach (self::CONFIG_MAP as $ident) {
                $variableID = @IPS_GetObjectIDByIdent($ident, $catID);
                if ($variableID !== false) {
                    $ids[] = $variableID;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function BuildConfig(): ?array
    {
        $catID = $this->ReadPropertyInteger('SettingsCategory');
        if ($catID <= 0 || !IPS_ObjectExists($catID)) {
            return null;
        }

        $out = [];
        foreach (self::CONFIG_MAP as $field => $ident) {
            $variableID = @IPS_GetObjectIDByIdent($ident, $catID);
            if ($variableID !== false && IPS_VariableExists($variableID)) {
                $out[$field] = GetValue($variableID);
            }
        }

        return count($out) > 0 ? $out : null;
    }

    private function BuildPayload(): array
    {
        // Netzleistung:
        // Hauptvariable = Bezug/Gesamtleistung.
        // Optional kann Rücklieferung als separate positive Variable angegeben werden.
        $gridBase = $this->ReadVar('L1');

        if ($this->ReadPropertyBoolean('InvertGridPower')) {
            $gridBase *= -1;
        }

        $gridExportPower = 0.0;
        $gridExportPowerID = $this->ReadPropertyInteger('GridExportPower');
        if ($gridExportPowerID > 0 && IPS_VariableExists($gridExportPowerID)) {
            $gridExportPower = (float) GetValue($gridExportPowerID);
        }

        $grid = $gridBase - $gridExportPower;

        $pvs = [];
        $batteries = [];

        // PV-Anlagen.
        $decodedPVs = json_decode($this->ReadPropertyString('Producers'), true);
        if (is_array($decodedPVs)) {
            foreach ($decodedPVs as $source) {
                $variableID = (int) ($source['VariableID'] ?? 0);
                if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                    continue;
                }

                $energyVariableID = (int) ($source['EnergyVariableID'] ?? 0);

                $pvs[] = [
                    'name'   => trim((string) ($source['Name'] ?? '')) !== ''
                        ? (string) $source['Name']
                        : 'PV ' . (count($pvs) + 1),
                    'value'  => (float) GetValue($variableID),
                    'energy' => ($energyVariableID > 0 && IPS_VariableExists($energyVariableID))
                        ? GetValueFormatted($energyVariableID)
                        : '',
                ];
            }
        }

        // Legacy-PV als Fallback.
        if (count($pvs) === 0) {
            $legacyPV = [
                [
                    'name'     => $this->ReadPropertyString('PV1Name'),
                    'powerID'  => $this->ReadPropertyInteger('SolarFlowPV'),
                    'energyID' => $this->ReadPropertyInteger('PV1Energy'),
                ],
                [
                    'name'     => $this->ReadPropertyString('PV2Name'),
                    'powerID'  => $this->ReadPropertyInteger('HoymilesPV'),
                    'energyID' => $this->ReadPropertyInteger('PV2Energy'),
                ],
            ];

            foreach ($legacyPV as $entry) {
                if ($entry['powerID'] <= 0 || !IPS_VariableExists($entry['powerID'])) {
                    continue;
                }

                $pvs[] = [
                    'name'   => $entry['name'],
                    'value'  => (float) GetValue($entry['powerID']),
                    'energy' => ($entry['energyID'] > 0 && IPS_VariableExists($entry['energyID']))
                        ? GetValueFormatted($entry['energyID'])
                        : '',
                ];
            }
        }

        // Batterien.
        $decodedBatteries = json_decode($this->ReadPropertyString('Batteries'), true);
        if (is_array($decodedBatteries)) {
            foreach ($decodedBatteries as $source) {
                $variableID = (int) ($source['VariableID'] ?? 0);
                if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                    continue;
                }

                $energyVariableID = (int) ($source['EnergyVariableID'] ?? 0);
                $socVariableID = (int) ($source['SoCVariableID'] ?? 0);

                $value = (float) GetValue($variableID);
                if ((bool) ($source['InvertFlow'] ?? false)) {
                    $value *= -1;
                }

                $batteries[] = [
                    'name'   => trim((string) ($source['Name'] ?? '')) !== ''
                        ? (string) $source['Name']
                        : 'Batterie ' . (count($batteries) + 1),
                    'value'  => $value,
                    'energy' => ($energyVariableID > 0 && IPS_VariableExists($energyVariableID))
                        ? GetValueFormatted($energyVariableID)
                        : '',
                    'soc'    => ($socVariableID > 0 && IPS_VariableExists($socVariableID))
                        ? (float) GetValue($socVariableID)
                        : 0.0,
                ];
            }
        }

        // Legacy-Batterie als Fallback.
        if (count($batteries) === 0) {
            $legacyBatteryID = $this->ReadPropertyInteger('BatteryOut');
            if ($legacyBatteryID > 0 && IPS_VariableExists($legacyBatteryID)) {
                $legacySoCID = $this->ReadPropertyInteger('BatterySoC');

                $batteries[] = [
                    'name'   => 'Batterie',
                    'value'  => (float) GetValue($legacyBatteryID),
                    'energy' => '',
                    'soc'    => ($legacySoCID > 0 && IPS_VariableExists($legacySoCID))
                        ? (float) GetValue($legacySoCID)
                        : 0.0,
                ];
            }
        }

        // Wallbox.
        $wallbox = [
            'name'   => $this->ReadPropertyString('WallboxName'),
            'value'  => $this->ReadVar('WallboxPower'),
            'energy' => $this->ReadVarFormatted('WallboxEnergy'),
        ];

        // Verbrauchergruppen.
        $groups = [];
        $decoded = json_decode($this->ReadPropertyString('Groups'), true);
        if (is_array($decoded)) {
            foreach ($decoded as $group) {
                $variableID = (int) ($group['VariableID'] ?? 0);
                $value = ($variableID > 0 && IPS_VariableExists($variableID))
                    ? (float) GetValue($variableID)
                    : 0.0;

                $dailyVariableID = (int) ($group['DailyVariableID'] ?? 0);
                $daily = ($dailyVariableID > 0 && IPS_VariableExists($dailyVariableID))
                    ? GetValueFormatted($dailyVariableID)
                    : '';

                $groups[] = [
                    'name'  => (string) ($group['Name'] ?? ''),
                    'icon'  => (string) ($group['Icon'] ?? 'plug'),
                    'value' => $value,
                    'daily' => $daily,
                ];
            }
        }

        $config = $this->BuildConfig();

        // Statistik.
        $stats = [];
        $statDefs = [
            ['DayProduction', 'Tagesproduktion'],
            ['WeekProduction', 'Wochenproduktion'],
            ['DayGridImport', 'Netzbezug heute'],
            ['WeekGridImport', 'Netzbezug Woche'],
        ];

        foreach ($statDefs as [$property, $label]) {
            $variableID = $this->ReadPropertyInteger($property);
            if ($variableID > 0 && IPS_VariableExists($variableID)) {
                $stats[] = [
                    'label' => $label,
                    'value' => GetValueFormatted($variableID),
                ];
            }
        }

        return [
            'displayMode'      => $this->ReadPropertyString('DisplayMode'),
            'pvs'              => $pvs,
            'batteries'        => $batteries,
            'grid'             => $grid,
            'gridImportEnergy' => $this->ReadVarFormatted('GridImportEnergy'),
            'gridExportEnergy' => $this->ReadVarFormatted('GridExportEnergy'),
            'wallbox'          => $wallbox,
            'hasWallbox'       => (
                $this->ReadPropertyInteger('WallboxPower') > 0
                && IPS_VariableExists($this->ReadPropertyInteger('WallboxPower'))
            ),
            'groups'           => $groups,
            'stats'            => $stats,
            'hasConfig'        => $config !== null,
            'config'           => $config ?? (object) [],
        ];
    }
}
