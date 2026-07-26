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

    /* Hausansicht V3 – Gebäude und Energiepfade in EINEM Koordinatensystem */
    #house-stage {
        position: relative;
        width: 1000px;
        height: 640px;
        display: __HOUSE_DISPLAY__;
        overflow: hidden;
        border-radius: 18px;
        box-sizing: border-box;
        color: #eef4fa;
        border: 1px solid #253342;
        background:
            radial-gradient(circle at 52% 30%, rgba(42, 71, 94, 0.30), transparent 42%),
            linear-gradient(180deg, #101923 0%, #0a1118 56%, #070b10 100%);
    }

    #house-scene {
        position: absolute;
        inset: 0;
        width: 1000px;
        height: 640px;
        z-index: 1;
    }

    #house-scene .scene-ground {
        fill: #0b1712;
        opacity: .82;
    }

    #house-scene .house-wall {
        fill: #171f29;
        stroke: #3a4858;
        stroke-width: 2.5;
    }

    #house-scene .house-side {
        fill: #111821;
        stroke: #344251;
        stroke-width: 2;
    }

    #house-scene .roof {
        fill: #222c38;
        stroke: #536172;
        stroke-width: 2.5;
    }

    #house-scene .roof-edge {
        fill: none;
        stroke: #6c7885;
        stroke-width: 3;
        opacity: .72;
    }

    #house-scene .window {
        fill: #132434;
        stroke: #526578;
        stroke-width: 1.5;
    }

    #house-scene .window-glow {
        fill: #d99a38;
        opacity: .62;
    }

    #house-scene .panel {
        fill: #152c4a;
        stroke: #7c93af;
        stroke-width: 1.25;
    }

    #house-scene .panel-grid {
        stroke: #68809d;
        stroke-width: .8;
        opacity: .8;
    }

    #house-scene .device {
        fill: #e5eaee;
        stroke: #7d8893;
        stroke-width: 1.7;
    }

    #house-scene .device-dark {
        fill: #18212a;
        stroke: #657482;
        stroke-width: 1.5;
    }

    #house-scene .grid-metal {
        fill: none;
        stroke: #9aa9b8;
        stroke-width: 2;
        opacity: .82;
    }

    #house-scene .flow-base {
        fill: none;
        stroke: #31404e;
        stroke-width: 3;
        stroke-linecap: round;
        stroke-linejoin: round;
        opacity: .68;
    }

    #house-scene .flow-path {
        fill: none;
        stroke-width: 4;
        stroke-linecap: round;
        stroke-linejoin: round;
        opacity: .98;
        filter: drop-shadow(0 0 3px currentColor);
    }

    #house-scene .device-caption {
        fill: #aeb9c5;
        font-size: 12px;
        font-weight: 600;
    }

    #house-scene .small-caption {
        fill: #8493a2;
        font-size: 10px;
    }

    .house-label {
        position: absolute;
        z-index: 5;
        min-width: 112px;
        padding: 7px 9px;
        box-sizing: border-box;
        border: 1px solid rgba(129, 151, 172, .28);
        border-radius: 9px;
        background: rgba(8, 13, 19, .82);
        box-shadow: 0 6px 18px rgba(0,0,0,.22);
        line-height: 1.25;
        pointer-events: none;
    }

    .house-label .name {
        color: #b8c3ce;
        font-size: 11px;
        font-weight: 650;
        margin-bottom: 2px;
    }

    .house-label .power {
        font-size: 17px;
        font-weight: 700;
        color: #f4f7f9;
    }

    .house-label .sub {
        font-size: 10px;
        margin-top: 2px;
        color: #99a7b5;
    }

    #house-grid-label {
        left: 28px;
        top: 236px;
        width: 128px;
    }

    #house-home-label {
        left: 438px;
        top: 338px;
        width: 150px;
        text-align: center;
    }

    #house-battery-label {
        right: 28px;
        top: 245px;
        width: 142px;
    }

    #house-wallbox-label {
        left: 168px;
        top: 375px;
        width: 150px;
    }

    #house-pv-list {
        position: absolute;
        left: 315px;
        right: 245px;
        top: 55px;
        z-index: 5;
        display: flex;
        justify-content: center;
        gap: 9px;
        flex-wrap: wrap;
        pointer-events: none;
    }

    .house-pv-chip {
        min-width: 106px;
        padding: 5px 8px;
        box-sizing: border-box;
        text-align: center;
        border-radius: 9px;
        background: rgba(10, 15, 22, .84);
        border: 1px solid rgba(239, 160, 32, .38);
        box-shadow: 0 4px 14px rgba(0,0,0,.18);
    }

    .house-pv-chip .name {
        color: #efa020;
        font-size: 10px;
        font-weight: 700;
    }

    .house-pv-chip .power {
        color: #f4f6f8;
        font-size: 13px;
        font-weight: 700;
        margin-top: 1px;
    }

    .house-pv-chip .energy {
        color: #9eabb7;
        font-size: 9px;
        margin-top: 1px;
    }

    .house-card-grid {
        position: absolute;
        left: 16px;
        right: 16px;
        bottom: 14px;
        height: 126px;
        z-index: 6;
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
    }

    .house-card {
        min-width: 0;
        padding: 9px 10px;
        box-sizing: border-box;
        overflow: hidden;
        border: 1px solid #2e3c4b;
        border-radius: 11px;
        background: rgba(11, 17, 24, .94);
        box-shadow: 0 8px 22px rgba(0,0,0,.18);
    }

    .house-card .head {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 5px;
        color: #eaf0f5;
        font-size: 11px;
        font-weight: 700;
    }

    .house-card .big {
        margin-bottom: 2px;
        font-size: 18px;
        font-weight: 700;
    }

    .house-card .small {
        color: #9caab7;
        font-size: 9px;
        line-height: 1.3;
    }

    .house-card .sep {
        height: 1px;
        margin: 5px 0;
        background: #253342;
    }

    .house-card .list {
        max-height: 42px;
        overflow: hidden;
        color: #aeb8c2;
        font-size: 8.5px;
        line-height: 1.3;
    }

    .c-solar { color: #EFA020; }
    .c-import { color: #ff4d43; }
    .c-export { color: #6fd32f; }
    .c-discharge { color: #3ca0ff; }
    .c-charge { color: #6fd32f; }
    .c-wallbox { color: #22d3d0; }
    .c-home { color: #4d9fff; }

    @media (max-width: 700px) {
        .house-card-grid { gap: 5px; }
        .house-card { padding: 7px; }
    }

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

                    <!-- Hausansicht V3: komplettes Gebäude als SVG -->
                    <div id="house-stage">
                        <svg id="house-scene" viewBox="0 0 1000 640" aria-hidden="true">
                            <defs>
                                <linearGradient id="batteryBody" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#283442"></stop>
                                    <stop offset="100%" stop-color="#111820"></stop>
                                </linearGradient>
                                <linearGradient id="garageGlass" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#24384c"></stop>
                                    <stop offset="100%" stop-color="#0c131a"></stop>
                                </linearGradient>
                            </defs>

                            <!-- Umgebung -->
                            <ellipse class="scene-ground" cx="510" cy="450" rx="420" ry="58"></ellipse>
                            <path d="M0 430 C110 392 170 414 255 432 C350 451 430 428 520 427 C625 426 710 451 802 438 C880 427 940 414 1000 430 L1000 500 L0 500 Z"
                                  fill="#0e2117" opacity=".62"></path>

                            <!-- Netz -->
                            <g id="grid-object">
                                <path class="grid-metal" d="M88 335 L116 205 L144 335"></path>
                                <line class="grid-metal" x1="99" y1="286" x2="133" y2="286"></line>
                                <line class="grid-metal" x1="94" y1="257" x2="138" y2="257"></line>
                                <line class="grid-metal" x1="88" y1="228" x2="144" y2="228"></line>
                                <line class="grid-metal" x1="82" y1="335" x2="150" y2="335"></line>
                                <text x="116" y="358" text-anchor="middle" class="device-caption">Netz</text>
                            </g>

                            <!-- Hauskörper -->
                            <g id="house-object">
                                <!-- Garage -->
                                <path class="house-side" d="M205 390 L205 305 L315 232 L430 232 L485 286 L485 430 L205 430 Z"></path>
                                <rect x="226" y="323" width="188" height="107" rx="3" fill="url(#garageGlass)" stroke="#364554" stroke-width="2"></rect>
                                <line x1="226" y1="350" x2="414" y2="350" stroke="#283847" stroke-width="1.4"></line>
                                <line x1="226" y1="377" x2="414" y2="377" stroke="#283847" stroke-width="1.4"></line>

                                <!-- Haupthaus -->
                                <path class="house-wall" d="M392 430 L392 245 L508 165 L675 165 L790 248 L790 430 Z"></path>
                                <path class="roof" d="M352 255 L498 128 L682 128 L828 255 L785 274 L662 194 L518 194 L395 274 Z"></path>
                                <path class="roof-edge" d="M352 255 L498 128 L682 128 L828 255"></path>

                                <!-- Fenster / Tür -->
                                <rect class="window" x="442" y="286" width="58" height="76" rx="2"></rect>
                                <rect class="window-glow" x="451" y="296" width="40" height="56" rx="1"></rect>
                                <line x1="471" y1="296" x2="471" y2="352" stroke="#304252" stroke-width="1.4"></line>

                                <rect class="window" x="684" y="285" width="60" height="78" rx="2"></rect>
                                <rect x="704" y="302" width="22" height="61" rx="2" fill="#10161d" stroke="#3d4c5b" stroke-width="1.4"></rect>

                                <!-- PV -->
                                <g id="pv-panels">
                                    <polygon class="panel" points="474,150 540,150 569,184 500,184"></polygon>
                                    <polygon class="panel" points="545,150 612,150 642,184 574,184"></polygon>
                                    <polygon class="panel" points="617,150 674,150 706,184 647,184"></polygon>
                                    <line class="panel-grid" x1="496" y1="150" x2="522" y2="184"></line>
                                    <line class="panel-grid" x1="518" y1="150" x2="544" y2="184"></line>
                                    <line class="panel-grid" x1="567" y1="150" x2="596" y2="184"></line>
                                    <line class="panel-grid" x1="590" y1="150" x2="619" y2="184"></line>
                                    <line class="panel-grid" x1="641" y1="150" x2="672" y2="184"></line>
                                    <line class="panel-grid" x1="488" y1="167" x2="690" y2="167"></line>
                                </g>

                                <!-- Wechselrichter -->
                                <g id="inverter-object">
                                    <rect class="device" x="545" y="240" width="72" height="86" rx="10"></rect>
                                    <rect class="device-dark" x="563" y="270" width="36" height="27" rx="5"></rect>
                                    <circle cx="575" cy="283" r="3.2" fill="#6fd32f"></circle>
                                    <circle cx="587" cy="283" r="3.2" fill="#3ca0ff"></circle>
                                    <text x="581" y="340" text-anchor="middle" class="small-caption">Wechselrichter</text>
                                </g>
                            </g>

                            <!-- Wallbox -->
                            <g id="wallbox-object">
                                <rect class="device" x="237" y="346" width="42" height="59" rx="8"></rect>
                                <path d="M258 356 L248 375 H256 L252 393 L270 368 H261 L267 356 Z" fill="#22d3d0"></path>
                                <path d="M277 382 C302 384 319 393 325 408" fill="none" stroke="#657789" stroke-width="2"></path>
                                <text x="258" y="423" text-anchor="middle" class="device-caption">Wallbox</text>
                            </g>

                            <!-- Auto -->
                            <g id="car-object">
                                <path d="M280 403 C296 373 329 359 370 359 C407 359 436 374 450 403 Z"
                                      fill="#c9d1d8" stroke="#697784" stroke-width="2"></path>
                                <rect x="270" y="397" width="190" height="34" rx="15"
                                      fill="#d6dce1" stroke="#697784" stroke-width="2"></rect>
                                <path d="M319 373 L350 365 L394 365 L417 381 L323 381 Z"
                                      fill="#192837" stroke="#687988" stroke-width="1.5"></path>
                                <circle cx="310" cy="428" r="13" fill="#080b0f" stroke="#4d5a66" stroke-width="2"></circle>
                                <circle cx="422" cy="428" r="13" fill="#080b0f" stroke="#4d5a66" stroke-width="2"></circle>
                            </g>

                            <!-- Batterie -->
                            <g id="battery-object">
                                <rect x="823" y="270" width="86" height="154" rx="13"
                                      fill="url(#batteryBody)" stroke="#687889" stroke-width="2.2"></rect>
                                <rect x="853" y="258" width="27" height="12" rx="3" fill="#6c7885"></rect>
                                <rect x="837" y="290" width="58" height="112" rx="7"
                                      fill="#101820" stroke="#41505e" stroke-width="1.5"></rect>
                                <rect id="house-battery-fill" x="837" y="290" width="58" height="112" rx="7"
                                      fill="#6fd32f" opacity=".80"></rect>
                                <line x1="837" y1="327" x2="895" y2="327" stroke="#18242d" stroke-width="2"></line>
                                <line x1="837" y1="364" x2="895" y2="364" stroke="#18242d" stroke-width="2"></line>
                                <text x="866" y="444" text-anchor="middle" class="device-caption">Batterie</text>
                            </g>

                            <!-- Statische Leitungsbasis -->
                            <g id="house-flow-base">
                                <path class="flow-base" d="M574 184 L574 240"></path>
                                <path class="flow-base" d="M150 300 L545 300"></path>
                                <path class="flow-base" d="M617 300 L823 300"></path>
                                <path class="flow-base" d="M581 326 L581 375"></path>
                                <path class="flow-base" d="M545 330 L470 330 L470 385 L279 385"></path>
                            </g>

                            <!-- Dynamische Energiepfade -->
                            <g id="house-flow-lines"></g>
                            <g id="house-flow-dots"></g>
                        </svg>

                        <div id="house-pv-list"></div>

                        <div id="house-grid-label" class="house-label">
                            <div class="name">Netz</div>
                            <div id="house-grid-power" class="power c-import">0 W</div>
                            <div id="house-grid-mode" class="sub"></div>
                        </div>

                        <div id="house-home-label" class="house-label">
                            <div class="name">Hausverbrauch</div>
                            <div id="house-home-power" class="power c-home">0 W</div>
                        </div>

                        <div id="house-battery-label" class="house-label">
                            <div class="name">Batterie</div>
                            <div id="house-battery-power" class="power c-discharge">0 W</div>
                            <div id="house-battery-mode" class="sub"></div>
                            <div id="house-battery-soc" class="sub"></div>
                        </div>

                        <div id="house-wallbox-label" class="house-label">
                            <div class="name" id="house-wallbox-name">Wallbox</div>
                            <div id="house-wallbox-power" class="power c-wallbox">0 W</div>
                            <div id="house-wallbox-energy" class="sub"></div>
                        </div>

                        <div class="house-card-grid">
                            <div class="house-card">
                                <div class="head"><span class="c-import">⚡</span> Netz</div>
                                <div id="card-grid-power" class="big c-import">0 W</div>
                                <div id="card-grid-mode" class="small"></div>
                                <div class="sep"></div>
                                <div id="card-grid-energy" class="small"></div>
                            </div>

                            <div class="house-card">
                                <div class="head"><span class="c-solar">☀</span> PV gesamt</div>
                                <div id="card-pv-power" class="big c-solar">0 W</div>
                                <div id="card-pv-energy" class="small"></div>
                                <div class="sep"></div>
                                <div id="card-pv-list" class="list"></div>
                            </div>

                            <div class="house-card">
                                <div class="head"><span class="c-home">⌂</span> Haus</div>
                                <div id="card-home-power" class="big c-home">0 W</div>
                                <div class="small">Verbrauch</div>
                            </div>

                            <div class="house-card">
                                <div class="head"><span class="c-charge">▯</span> Batterie</div>
                                <div id="card-bat-soc" class="big c-charge">0 %</div>
                                <div id="card-bat-power" class="small"></div>
                                <div class="sep"></div>
                                <div id="card-bat-list" class="list"></div>
                            </div>

                            <div class="house-card">
                                <div class="head"><span class="c-wallbox">ϟ</span> Wallbox</div>
                                <div id="card-wallbox-power" class="big c-wallbox">0 W</div>
                                <div id="card-wallbox-energy" class="small"></div>
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

        const pvTotal = pvs.reduce((sum, pv) => sum + (pv.value || 0), 0);
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);
        const mainBattery = batteries.length ? batteries[0] : null;
        const mainSoc = mainBattery ? (mainBattery.soc || 0) : 0;

        // PV oben.
        const pvList = document.getElementById('house-pv-list');
        pvList.innerHTML = pvs.map((pv, i) => `
            <div class="house-pv-chip">
                <div class="name">${pv.name || ('PV ' + (i + 1))}</div>
                <div class="power">${fmt(pv.value)}</div>
                ${pv.energy ? `<div class="energy">${pv.energy}</div>` : ''}
            </div>
        `).join('');

        // Hauptwerte.
        const gridColor = grid >= 0 ? AC.import : AC.export;
        const gridMode = grid >= 0 ? 'Bezug →' : 'Einspeisung ←';

        document.getElementById('house-grid-power').textContent = fmt(Math.abs(grid));
        document.getElementById('house-grid-power').style.color = gridColor;
        document.getElementById('house-grid-mode').textContent = gridMode;
        document.getElementById('house-grid-mode').style.color = gridColor;

        document.getElementById('house-home-power').textContent = fmt(haus);

        const batColor = batteryTotal >= 0 ? AC.discharge : AC.charge;
        const batMode = batteryTotal >= 0 ? '← Entladen' : '→ Laden';

        document.getElementById('house-battery-power').textContent = fmt(Math.abs(batteryTotal));
        document.getElementById('house-battery-power').style.color = batColor;
        document.getElementById('house-battery-mode').textContent = batMode;
        document.getElementById('house-battery-mode').style.color = batColor;
        document.getElementById('house-battery-soc').textContent = `${Math.round(mainSoc)} %`;

        // Batterie im Gebäude proportional zum SOC füllen.
        const houseBatteryFill = document.getElementById('house-battery-fill');
        if (houseBatteryFill) {
            const soc = Math.max(0, Math.min(100, mainSoc));
            const fullHeight = 112;
            const fillHeight = fullHeight * soc / 100;
            houseBatteryFill.setAttribute('y', 290 + (fullHeight - fillHeight));
            houseBatteryFill.setAttribute('height', fillHeight);
            houseBatteryFill.setAttribute('fill', batColor);
        }

        document.getElementById('house-wallbox-name').textContent = wallbox.name || 'Wallbox';
        document.getElementById('house-wallbox-power').textContent = fmt(wallbox.value || 0);
        document.getElementById('house-wallbox-energy').textContent = wallbox.energy || '';

        // Karten unten.
        document.getElementById('card-grid-power').textContent = fmt(Math.abs(grid));
        document.getElementById('card-grid-power').style.color = gridColor;
        document.getElementById('card-grid-mode').textContent = gridMode;
        document.getElementById('card-grid-mode').style.color = gridColor;

        const gridEnergy = [];
        if (d.gridImportEnergy) {
            gridEnergy.push(`<span class="c-import">Bezug: ${d.gridImportEnergy}</span>`);
        }
        if (d.gridExportEnergy) {
            gridEnergy.push(`<span class="c-export">Einspeisung: ${d.gridExportEnergy}</span>`);
        }
        document.getElementById('card-grid-energy').innerHTML = gridEnergy.join('<br>');

        document.getElementById('card-pv-power').textContent = fmt(pvTotal);
        const pvEnergyValues = sumFormattedEnergy(pvs.map(pv => pv.energy));
        document.getElementById('card-pv-energy').textContent =
            pvEnergyValues.length === 1 ? pvEnergyValues[0] : '';
        document.getElementById('card-pv-list').innerHTML = pvs.map((pv, i) =>
            `<div><span class="c-solar">${pv.name || ('PV ' + (i + 1))}</span> · ${fmt(pv.value)}${pv.energy ? ' · ' + pv.energy : ''}</div>`
        ).join('');

        document.getElementById('card-home-power').textContent = fmt(haus);

        document.getElementById('card-bat-soc').textContent = `${Math.round(mainSoc)} %`;
        document.getElementById('card-bat-power').innerHTML =
            `<span style="color:${batColor}">${fmt(Math.abs(batteryTotal))} · ${batMode}</span>`;
        document.getElementById('card-bat-list').innerHTML = batteries.map((bat, i) => {
            const c = (bat.value || 0) >= 0 ? AC.discharge : AC.charge;
            const m = (bat.value || 0) >= 0 ? 'Entladen' : 'Laden';
            return `<div><span style="color:${c}">${bat.name || ('Batterie ' + (i + 1))}</span> · ${Math.round(bat.soc || 0)} % · ${fmt(Math.abs(bat.value || 0))} ${m}</div>`;
        }).join('');

        document.getElementById('card-wallbox-power').textContent = fmt(wallbox.value || 0);
        document.getElementById('card-wallbox-energy').textContent = wallbox.energy || '';

        // Flusspfade direkt über dem Haus.
        if (pvTotal > 0) {
            addHouseEdge('house-pv', 'M574,184 L574,240', AC.solar);
            houseEdgeState['house-pv'] = { w: pvTotal, rev: false };
        }

        if (grid >= 0 && Math.abs(grid) > 0) {
            addHouseEdge('house-grid-import', 'M150,300 L545,300', AC.import);
            houseEdgeState['house-grid-import'] = { w: Math.abs(grid), rev: false };
        } else if (grid < 0) {
            addHouseEdge('house-grid-export', 'M150,300 L545,300', AC.export);
            houseEdgeState['house-grid-export'] = { w: Math.abs(grid), rev: true };
        }

        if (Math.abs(batteryTotal) > 0) {
            addHouseEdge('house-battery', 'M617,300 L823,300', batColor);
            // Pfad ist Wechselrichter -> Batterie. Entladen läuft rückwärts.
            houseEdgeState['house-battery'] = {
                w: Math.abs(batteryTotal),
                rev: batteryTotal >= 0
            };
        }

        if (haus > 0) {
            addHouseEdge('house-home', 'M581,326 L581,375', AC.home);
            houseEdgeState['house-home'] = {
                w: haus,
                rev: false
            };
        }

        if ((wallbox.value || 0) > 0) {
            addHouseEdge('house-wallbox', 'M545,330 L470,330 L470,385 L279,385', AC.wallbox);
            houseEdgeState['house-wallbox'] = {
                w: wallbox.value || 0,
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

        let graphWidth = mode === 'house' ? 1000 : 540;

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
            'groups'           => $groups,
            'stats'            => $stats,
            'hasConfig'        => $config !== null,
            'config'           => $config ?? (object) [],
        ];
    }
}
