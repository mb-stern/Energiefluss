<?php

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
<style>
    body {
        margin: 0;
        font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: transparent;
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

    /* Hausansicht – vollständig selbsttragendes SVG */
    #house-stage {
        position: relative;
        width: 900px;
        height: 640px;
        display: __HOUSE_DISPLAY__;
        overflow: hidden;
        border-radius: 18px;
        box-sizing: border-box;
        border: 1px solid #263443;
        background: #111827;
    }

    #house-svg {
        position: absolute;
        inset: 0;
        width: 900px;
        height: 640px;
        overflow: visible;
    }

    #house-svg .house-shape {
        fill: #17202c;
        stroke: #4b5563;
        stroke-width: 4;
        stroke-linejoin: round;
    }

    #house-svg .roof-shape {
        fill: #1f2937;
        stroke: #5f6b7a;
        stroke-width: 4;
        stroke-linejoin: round;
    }

    #house-svg .garage-shape {
        fill: #141c27;
        stroke: #4b5563;
        stroke-width: 4;
        stroke-linejoin: round;
    }

    #house-svg .window {
        fill: #243447;
        stroke: #64748b;
        stroke-width: 2;
    }

    #house-svg .panel {
        fill: #13283f;
        stroke: #8394aa;
        stroke-width: 1.6;
    }

    #house-svg .panel-line {
        stroke: #657b95;
        stroke-width: 1;
        opacity: .85;
    }

    #house-svg .device {
        fill: #1f2937;
        stroke: #64748b;
        stroke-width: 2.5;
    }

    #house-svg .device-light {
        fill: #dce3e9;
        stroke: #8492a0;
        stroke-width: 2.5;
    }

    #house-svg .base-path {
        fill: none;
        stroke: #3b4654;
        stroke-width: 4;
        stroke-linecap: round;
        stroke-linejoin: round;
        opacity: .7;
    }

    #house-svg .flow-path {
        fill: none;
        stroke-width: 5;
        stroke-linecap: round;
        stroke-linejoin: round;
        opacity: .98;
        filter: drop-shadow(0 0 3px currentColor);
    }

    #house-svg .node-box {
        fill: rgba(15, 23, 34, .94);
        stroke-width: 1.5;
    }

    #house-svg .node-title {
        fill: #9ca3af;
        font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 12px;
        font-weight: 600;
    }

    #house-svg .node-value {
        fill: #fff;
        font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 20px;
        font-weight: 700;
    }

    #house-svg .node-detail {
        fill: #9ca3af;
        font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 10px;
    }

    #house-svg .device-label {
        fill: #aeb8c3;
        font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 10px;
        font-weight: 600;
        text-anchor: middle;
    }

    .c-solar { fill: #EFA020 !important; color: #EFA020 !important; }
    .c-import { fill: #ff4d43 !important; color: #ff4d43 !important; }
    .c-export { fill: #6fd32f !important; color: #6fd32f !important; }
    .c-discharge { fill: #3ca0ff !important; color: #3ca0ff !important; }
    .c-charge { fill: #6fd32f !important; color: #6fd32f !important; }
    .c-wallbox { fill: #22d3d0 !important; color: #22d3d0 !important; }
    .c-home { fill: #4d9fff !important; color: #4d9fff !important; }

</style>
<script src="/icons.js"></script>

<div id="eflow">
    <div id="scale-host">
        <div id="scale-root">
            <div id="wrap">
                <div id="fit">

                    <!-- Klassische Energieflussansicht -->
                    <div id="stage">
                        <svg id="svg" width="1080" height="640" viewBox="0 0 1080 640" aria-hidden="true">
                            <g id="lines" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></g>
                            <g id="ring"></g>
                            <g id="dots"></g>
                        </svg>
                    </div>

                    <!-- Hausansicht: vollständig selbsttragendes SVG -->
                    <div id="house-stage">
                        <svg id="house-svg" viewBox="0 0 900 640" aria-hidden="true">
                            <!-- Gebäude -->
                            <g id="house-background">
                                <path class="house-shape"
                                      d="M270 260 L450 125 L650 260 L650 500 L270 500 Z"></path>
                                <path class="roof-shape"
                                      d="M235 270 L440 95 L470 95 L685 270 L648 290 L455 150 L275 290 Z"></path>

                                <!-- Carport/Garage links -->
                                <path class="garage-shape"
                                      d="M80 350 L270 260 L310 295 L310 500 L80 500 Z"></path>
                                <path class="roof-shape"
                                      d="M60 350 L245 245 L310 295 L285 315 L242 285 L85 375 Z"></path>

                                <!-- Fenster/Tür -->
                                <rect class="window" x="505" y="300" width="72" height="88" rx="3"></rect>
                                <line x1="541" y1="300" x2="541" y2="388" stroke="#64748b" stroke-width="1.5"></line>
                                <rect class="window" x="590" y="322" width="38" height="178" rx="3"></rect>

                                <!-- PV Hausdach -->
                                <g id="house-pv-panels">
                                    <polygon class="panel" points="360,150 420,150 451,190 387,190"></polygon>
                                    <polygon class="panel" points="425,150 485,150 519,190 455,190"></polygon>
                                    <polygon class="panel" points="490,150 550,150 586,190 523,190"></polygon>
                                    <polygon class="panel" points="387,194 451,194 482,234 414,234"></polygon>
                                    <polygon class="panel" points="455,194 519,194 553,234 486,234"></polygon>
                                    <polygon class="panel" points="523,194 586,194 622,234 557,234"></polygon>
                                </g>

                                <!-- PV Carport -->
                                <g id="carport-pv-panels">
                                    <polygon class="panel" points="105,318 150,294 190,316 145,340"></polygon>
                                    <polygon class="panel" points="154,292 199,268 239,290 194,314"></polygon>
                                    <polygon class="panel" points="149,343 194,319 234,341 189,365"></polygon>
                                    <polygon class="panel" points="198,317 243,293 283,315 238,339"></polygon>
                                </g>

                                <!-- Smartmeter: zentraler Verteiler -->
                                <g id="smartmeter-object">
                                    <rect class="device-light" x="407" y="327" width="86" height="105" rx="10"></rect>
                                    <rect class="device" x="426" y="352" width="48" height="34" rx="5"></rect>
                                    <circle cx="438" cy="369" r="4" fill="#6fd32f"></circle>
                                    <circle cx="461" cy="369" r="4" fill="#3ca0ff"></circle>
                                    <text x="450" y="448" class="device-label">Smartmeter</text>
                                </g>

                                <!-- Batterie -->
                                <g id="battery-object">
                                    <rect class="device-light" x="322" y="412" width="62" height="116" rx="8"></rect>
                                    <line x1="332" y1="448" x2="374" y2="448" stroke="#9aa6b2" stroke-width="2"></line>
                                    <line x1="332" y1="482" x2="374" y2="482" stroke="#9aa6b2" stroke-width="2"></line>
                                    <text x="353" y="545" class="device-label">Batterie</text>
                                </g>

                                <!-- Wallbox -->
                                <g id="wallbox-object">
                                    <rect class="device-light" x="126" y="404" width="48" height="66" rx="8"></rect>
                                    <path d="M151 413 L139 435 H148 L144 456 L164 428 H154 L162 413 Z"
                                          fill="#22d3d0"></path>
                                    <text x="150" y="486" class="device-label">Wallbox</text>
                                </g>

                                <!-- Netzsymbol -->
                                <g id="grid-object">
                                    <path d="M760 255 L790 410 M820 255 L790 410"
                                          fill="none" stroke="#8793a0" stroke-width="3"></path>
                                    <line x1="772" y1="315" x2="808" y2="315" stroke="#8793a0" stroke-width="3"></line>
                                    <line x1="765" y1="350" x2="815" y2="350" stroke="#8793a0" stroke-width="3"></line>
                                    <line x1="750" y1="410" x2="830" y2="410" stroke="#8793a0" stroke-width="3"></line>
                                    <text x="790" y="430" class="device-label">Netz</text>
                                </g>
                            </g>

                            <!-- Feste Grundverbindungen. Smartmeter = zentraler Knoten. -->
                            <g id="house-base-lines">
                                <path id="base-pv1" class="base-path"
                                      d="M450 327 L450 240"></path>
                                <path id="base-pv2" class="base-path"
                                      d="M407 375 L260 375 L260 350 L210 350"></path>
                                <path id="base-home" class="base-path"
                                      d="M493 375 L570 375"></path>
                                <path id="base-battery" class="base-path"
                                      d="M425 432 L384 470"></path>
                                <path id="base-grid" class="base-path"
                                      d="M493 405 L690 405 L690 455 L790 455"></path>
                                <path id="base-wallbox" class="base-path"
                                      d="M407 405 L260 405 L260 437 L174 437"></path>
                            </g>

                            <!-- Dynamische farbige Leitungen und Punkte -->
                            <g id="house-flow-lines"></g>
                            <g id="house-flow-dots"></g>

                            <!-- Werte -->
                            <g id="node-pv1" transform="translate(350 38)">
                                <rect class="node-box" width="150" height="66" rx="9" stroke="#EFA020"></rect>
                                <text id="svg-pv1-name" x="12" y="20" class="node-title">PV Dach</text>
                                <text id="svg-pv1-value" x="12" y="45" class="node-value c-solar">0 W</text>
                                <text id="svg-pv1-energy" x="12" y="59" class="node-detail"></text>
                            </g>

                            <g id="node-pv2" transform="translate(68 260)">
                                <rect class="node-box" width="145" height="66" rx="9" stroke="#EFA020"></rect>
                                <text id="svg-pv2-name" x="12" y="20" class="node-title">PV Carport</text>
                                <text id="svg-pv2-value" x="12" y="45" class="node-value c-solar">0 W</text>
                                <text id="svg-pv2-energy" x="12" y="59" class="node-detail"></text>
                            </g>

                            <g id="node-home" transform="translate(555 315)">
                                <rect class="node-box" width="150" height="62" rx="9" stroke="#4d9fff"></rect>
                                <text x="12" y="20" class="node-title">Hausverbrauch</text>
                                <text id="svg-home-value" x="12" y="46" class="node-value c-home">0 W</text>
                            </g>

                            <g id="node-battery" transform="translate(250 500)">
                                <rect class="node-box" width="155" height="74" rx="9" stroke="#3ca0ff"></rect>
                                <text id="svg-battery-name" x="12" y="20" class="node-title">Batterie</text>
                                <text id="svg-battery-value" x="12" y="45" class="node-value c-discharge">0 W</text>
                                <text id="svg-battery-detail" x="12" y="62" class="node-detail"></text>
                            </g>

                            <g id="node-grid" transform="translate(735 458)">
                                <rect class="node-box" width="150" height="86" rx="9" stroke="#ff4d43"></rect>
                                <text x="12" y="20" class="node-title">Netz</text>
                                <text id="svg-grid-value" x="12" y="45" class="node-value c-import">0 W</text>
                                <text id="svg-grid-mode" x="12" y="61" class="node-detail"></text>
                                <text id="svg-grid-energy" x="12" y="76" class="node-detail"></text>
                            </g>

                            <g id="node-wallbox" transform="translate(52 495)">
                                <rect class="node-box" width="150" height="72" rx="9" stroke="#22d3d0"></rect>
                                <text id="svg-wallbox-name" x="12" y="20" class="node-title">Wallbox</text>
                                <text id="svg-wallbox-value" x="12" y="45" class="node-value c-wallbox">0 W</text>
                                <text id="svg-wallbox-energy" x="12" y="62" class="node-detail"></text>
                            </g>
                        </svg>
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

    // ---------- Hausansicht V2 ----------
    const houseStage = document.getElementById('house-stage');
    const houseFlowLines = document.getElementById('house-flow-lines');
    const houseFlowDots = document.getElementById('house-flow-dots');

    const houseLineEl = {};
    const houseDotEl = {};
    let houseEdgeState = {};
    let houseEdgePhase = {};

    function clearHouseEdges() {
        Object.keys(houseLineEl).forEach(k => {
            houseLineEl[k].remove();
            houseDotEl[k].forEach(d => d.remove());
            delete houseLineEl[k];
            delete houseDotEl[k];
        });
        houseEdgeState = {};
    }

    function addHouseEdge(key, d, color, width = 3.2) {
        const p = document.createElementNS(NSc, 'path');
        p.setAttribute('d', d);
        p.setAttribute('class', 'flow-path');
        p.style.stroke = color;
        p.style.color = color;
        p.style.strokeWidth = String(width);
        houseFlowLines.appendChild(p);
        houseLineEl[key] = p;

        houseDotEl[key] = [0, 1, 2].map(() => {
            const c = document.createElementNS(NSc, 'circle');
            c.setAttribute('r', 4.5);
            c.setAttribute('fill', color);
            c.style.filter = `drop-shadow(0 0 3px ${color})`;
            houseFlowDots.appendChild(c);
            return c;
        });
    }

    function sumFormattedEnergy(entries) {
        // Formatierte Werte können unterschiedliche Profile besitzen.
        // Deshalb werden sie nicht rechnerisch addiert; die Einzelwerte
        // werden stattdessen unten aufgelistet.
        return entries.filter(Boolean);
    }

    function buildHouseView(d, grid, haus, pvs, batteries, wallbox) {
        clearHouseEdges();

        const pv1 = pvs.length > 0 ? pvs[0] : null;
        const pv2 = pvs.length > 1 ? pvs[1] : null;
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);
        const mainBattery = batteries.length ? batteries[0] : null;
        const mainSoc = mainBattery
            ? Math.max(0, Math.min(100, mainBattery.soc || 0))
            : 0;
        const hasWallbox = !!d.hasWallbox;

        // PV Dach
        document.getElementById('svg-pv1-name').textContent = pv1?.name || 'PV Dach';
        document.getElementById('svg-pv1-value').textContent = fmt(pv1?.value || 0);
        document.getElementById('svg-pv1-energy').textContent = pv1?.energy || '';

        // PV Carport: zweite PV nur dann zeigen, wenn sie konfiguriert ist.
        const pv2Visible = !!pv2;
        document.getElementById('node-pv2').style.display = pv2Visible ? '' : 'none';
        document.getElementById('carport-pv-panels').style.display = pv2Visible ? '' : 'none';
        document.getElementById('base-pv2').style.display = pv2Visible ? '' : 'none';
        document.getElementById('svg-pv2-name').textContent = pv2?.name || 'PV Carport';
        document.getElementById('svg-pv2-value').textContent = fmt(pv2?.value || 0);
        document.getElementById('svg-pv2-energy').textContent = pv2?.energy || '';

        // Haus
        document.getElementById('svg-home-value').textContent = fmt(haus);

        // Batterie
        const batColor = batteryTotal >= 0 ? AC.discharge : AC.charge;
        const batMode = batteryTotal >= 0 ? 'Entladen' : 'Laden';
        const batValue = document.getElementById('svg-battery-value');
        batValue.textContent = fmt(Math.abs(batteryTotal));
        batValue.style.fill = batColor;
        document.getElementById('svg-battery-name').textContent =
            mainBattery?.name || 'Batterie';
        document.getElementById('svg-battery-detail').textContent =
            `${Math.round(mainSoc)} % · ${batMode}`;

        // Netz
        const gridColor = grid >= 0 ? AC.import : AC.export;
        const gridMode = grid >= 0 ? 'Bezug' : 'Einspeisung';
        const gridValue = document.getElementById('svg-grid-value');
        gridValue.textContent = fmt(Math.abs(grid));
        gridValue.style.fill = gridColor;
        document.getElementById('svg-grid-mode').textContent = gridMode;

        const gridEnergy = [];
        if (d.gridImportEnergy) gridEnergy.push('Bezug ' + d.gridImportEnergy);
        if (d.gridExportEnergy) gridEnergy.push('Einspeisung ' + d.gridExportEnergy);
        document.getElementById('svg-grid-energy').textContent = gridEnergy.join(' · ');

        // Wallbox vollständig ausblenden, wenn keine Leistungsvariable konfiguriert ist.
        document.getElementById('wallbox-object').style.display = hasWallbox ? '' : 'none';
        document.getElementById('node-wallbox').style.display = hasWallbox ? '' : 'none';
        document.getElementById('base-wallbox').style.display = hasWallbox ? '' : 'none';
        document.getElementById('svg-wallbox-name').textContent =
            wallbox.name || 'Wallbox';
        document.getElementById('svg-wallbox-value').textContent =
            fmt(wallbox.value || 0);
        document.getElementById('svg-wallbox-energy').textContent =
            wallbox.energy || '';

        // ----------------------------------------------------------
        // Dynamische Pfade. Smartmeter ist der einzige Verteiler.
        // ----------------------------------------------------------

        // PV Dach -> Smartmeter
        if (pv1 && (pv1.value || 0) > 0) {
            addHouseEdge(
                'house-pv1',
                'M450 240 L450 327',
                AC.solar,
                5
            );
            houseEdgeState['house-pv1'] = {
                w: Math.abs(pv1.value || 0),
                rev: false
            };
        }

        // PV Carport -> Smartmeter
        if (pv2 && (pv2.value || 0) > 0) {
            addHouseEdge(
                'house-pv2',
                'M210 350 L260 350 L260 375 L407 375',
                AC.solar,
                5
            );
            houseEdgeState['house-pv2'] = {
                w: Math.abs(pv2.value || 0),
                rev: false
            };
        }

        // Smartmeter -> Haus
        if (haus > 0) {
            addHouseEdge(
                'house-home',
                'M493 375 L570 375',
                AC.home,
                5
            );
            houseEdgeState['house-home'] = {
                w: haus,
                rev: false
            };
        }

        // Smartmeter <-> Batterie
        if (Math.abs(batteryTotal) > 0) {
            addHouseEdge(
                'house-battery',
                'M425 432 L384 470',
                batColor,
                5
            );
            houseEdgeState['house-battery'] = {
                w: Math.abs(batteryTotal),
                // Pfad ist Smartmeter -> Batterie.
                // Entladen muss daher rückwärts laufen.
                rev: batteryTotal >= 0
            };
        }

        // Smartmeter <-> Netz
        if (Math.abs(grid) > 0) {
            addHouseEdge(
                'house-grid',
                'M493 405 L690 405 L690 455 L790 455',
                gridColor,
                5
            );
            houseEdgeState['house-grid'] = {
                w: Math.abs(grid),
                // Bezug: Netz -> Smartmeter.
                rev: grid >= 0
            };
        }

        // Smartmeter -> Wallbox
        if (hasWallbox && (wallbox.value || 0) > 0) {
            addHouseEdge(
                'house-wallbox',
                'M407 405 L260 405 L260 437 L174 437',
                AC.wallbox,
                5
            );
            houseEdgeState['house-wallbox'] = {
                w: Math.abs(wallbox.value || 0),
                rev: false
            };
        }
    }

    function applyDisplayMode(mode) {
        const house = mode === 'house';
        if (stage) stage.style.display = house ? 'none' : 'block';
        if (houseStage) houseStage.style.display = house ? 'block' : 'none';
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
        animateEdges(dt, houseEdgeState, houseLineEl, houseDotEl, houseEdgePhase);

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
HTML;

        return str_replace(
            ['__FLOW_DISPLAY__', '__HOUSE_DISPLAY__'],
            [$flowDisplay, $houseDisplay],
            $html
        );
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
