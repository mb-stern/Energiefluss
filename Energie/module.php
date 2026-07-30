<?php

/*
 * Integriert die Open-Source-Karte:
 * LordGuenni/power-flow-card
 * https://github.com/LordGuenni/power-flow-card
 *
 * Autor: Florian Stamer
 * Lizenz: MIT (laut package.json des Projekts)
 *
 * power-flow-card.js und Lit liegen versioniert im Modulbaum unter
 * assets/vendor/ und werden von ApplyChanges() lediglich in den
 * Web-Pfad /user/Energiefluss/vendor/ veröffentlicht.
 */

declare(strict_types=1);

class Energiefluss extends IPSModuleStrict
{

    public function Create(): void
    {
        parent::Create();

        // Dynamische Anlagen.
        $this->RegisterPropertyString('Producers', '[]');
        $this->RegisterPropertyString('Batteries', '[]');

        // Netz.
        $this->RegisterPropertyInteger('L1', 0);
        $this->RegisterPropertyInteger('GridExportPower', 0);
        $this->RegisterPropertyBoolean('InvertGridPower', false);
        $this->RegisterPropertyInteger('GridImportEnergy', 0);
        $this->RegisterPropertyInteger('GridExportEnergy', 0);

        // Wallbox.
        $this->RegisterPropertyString('WallboxName', 'Wallbox');
        $this->RegisterPropertyInteger('WallboxPower', 0);
        $this->RegisterPropertyInteger('WallboxEnergy', 0);
        $this->RegisterPropertyInteger('WallboxSoC', 0);

        // Weitere Darstellung.
        $this->RegisterPropertyString('Groups', '[]');

        // Farben der Visualisierung.
        $this->RegisterPropertyInteger('ColorSolar', 16766287);
        $this->RegisterPropertyInteger('ColorGridImport', 15684432);
        $this->RegisterPropertyInteger('ColorGridExport', 6732650);
        $this->RegisterPropertyInteger('ColorBatteryCharge', 6600182);
        $this->RegisterPropertyInteger('ColorBatteryDischarge', 2733814);
        $this->RegisterPropertyInteger('ColorConsumers', 3123599);
        $this->RegisterPropertyInteger('ColorWallbox', 3123599);
        $this->RegisterPropertyInteger('ColorHouseLoad', 5087231);

        // Farben der Haus-Visualisierung.
        // Vorgaben entsprechen den Originalfarben der eingebetteten home.svg.
        $this->RegisterPropertyInteger('HouseColorFacade', 2107187);       // #202733
        $this->RegisterPropertyInteger('HouseColorRoof', 1646636);         // #19202c
        $this->RegisterPropertyInteger('HouseColorRoofSecondary', 1712685);// #1a222d
        $this->RegisterPropertyInteger('HouseColorWindows', 16772536);     // #ffedb8
        $this->RegisterPropertyInteger('HouseColorSolarPanels', 10393218); // #9e9682
        $this->RegisterPropertyInteger('HouseColorInverter', 857372);      // #0d151c
        $this->RegisterPropertyInteger('HouseColorCar', 790808);           // #0c1118
        $this->RegisterPropertyInteger('HouseColorCarDetails', 1448741);   // #161b25
        $this->RegisterPropertyInteger('HouseColorBattery', 858148);       // #0d1824
        $this->RegisterPropertyInteger('HouseColorBatteryAccent', 6868216);// #68ccf8

        // Animationsgeschwindigkeit: 100 % entspricht dem bisherigen Verhalten.
        $this->RegisterPropertyInteger('FlowSpeedPercent', 100);

        // flow = technische Energieflussansicht, house = Hausansicht.
        $this->RegisterPropertyString('DisplayMode', 'flow');

        // full = alle technischen Details, compact = verdichtete Technikansicht.
        $this->RegisterPropertyString('TechnicalLayout', 'lite');

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
                        ['caption' => 'Technische Energieflussansicht', 'value' => 'flow'],
                        ['caption' => 'Hausansicht', 'value' => 'house'],
                    ],
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'TechnicalLayout',
                    'caption' => 'Technische Ansicht',
                    'options' => [
                        ['caption' => 'Compact – kompakte Originalansicht', 'value' => 'compact'],
                        ['caption' => 'Lite – mittlere Originalansicht', 'value' => 'lite'],
                        ['caption' => 'Full – vollständige Originalansicht', 'value' => 'full'],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'PV & Batterie',
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
                                    'width'   => '160px',
                                    'add'     => '',
                                    'edit'    => ['type' => 'ValidationTextBox'],
                                ],
                                [
                                    'caption' => 'Leistung',
                                    'name'    => 'VariableID',
                                    'width'   => '220px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'SOC',
                                    'name'    => 'SoCVariableID',
                                    'width'   => '180px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Max. Entladezustand (%)',
                                    'name'    => 'MaxDischargeSoC',
                                    'width'   => '190px',
                                    'add'     => 0,
                                    'edit'    => [
                                        'type'    => 'NumberSpinner',
                                        'minimum' => 0,
                                        'maximum' => 100,
                                        'digits'  => 0,
                                    ],
                                ],
                                [
                                    'caption' => 'Entladeenergie (kWh)',
                                    'name'    => 'DischargeEnergyVariableID',
                                    'width'   => '220px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Ladeenergie (kWh)',
                                    'name'    => 'ChargeEnergyVariableID',
                                    'width'   => '220px',
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
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Netz & Wallbox',
                    'items'   => [
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
                        [
                            'type'    => 'SelectObject',
                            'name'    => 'WallboxSoC',
                            'caption' => 'Fahrzeug-SOC (Variable oder Link)',
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Verbraucher (excl. Wallbox)',
                    'items'   => [
                        [
                            'type'     => 'List',
                            'name'     => 'Groups',
                            'caption'  => 'Verbraucher (excl. Wallbox)',
                            'rowCount' => 11,
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
                    'caption' => 'Energiefluss - Farben',
                    'items'   => [
                        ['type' => 'SelectColor', 'name' => 'ColorSolar', 'caption' => 'PV', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'ColorGridImport', 'caption' => 'Netzbezug', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'ColorGridExport', 'caption' => 'Netzeinspeisung', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'ColorBatteryCharge', 'caption' => 'Batterie laden', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'ColorBatteryDischarge', 'caption' => 'Batterie entladen', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'ColorHouseLoad', 'caption' => 'Hausverbrauch', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'ColorWallbox', 'caption' => 'Wallbox', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'ColorConsumers', 'caption' => 'Weitere Verbraucher', 'allowTransparent' => false],
                        [
                            'type' => 'HorizontalSlider',
                            'name' => 'FlowSpeedPercent',
                            'caption' => 'Animationsgeschwindigkeit (links langsam, rechts schnell)',
                            'minimum' => 25,
                            'maximum' => 300,
                            'stepSize' => 5,
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Hausansicht – Farben',
                    'items'   => [
                        ['type' => 'SelectColor', 'name' => 'HouseColorFacade', 'caption' => 'Haus / Fassade', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorRoof', 'caption' => 'Dach Hauptfläche', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorRoofSecondary', 'caption' => 'Dach Nebenfläche', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorWindows', 'caption' => 'Fenster / Licht', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorSolarPanels', 'caption' => 'PV-Module auf dem Haus', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorInverter', 'caption' => 'Wechselrichter', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorCar', 'caption' => 'Fahrzeug Karosserie', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorCarDetails', 'caption' => 'Fahrzeug Details', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorBattery', 'caption' => 'Batterie Gehäuse', 'allowTransparent' => false],
                        ['type' => 'SelectColor', 'name' => 'HouseColorBatteryAccent', 'caption' => 'Batterie Akzent', 'allowTransparent' => false],
                        [
                            'type'    => 'Button',
                            'caption' => 'Standardfarben wiederherstellen',
                            'onClick' => 'ENERGIE_ResetHouseColors($id);',
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
        if ($Ident === 'ToggleTechnicalLayout') {
            $requestedLayout = (string) $Value;
            $newLayout = in_array($requestedLayout, ['compact', 'lite', 'full'], true) ? $requestedLayout : 'lite';

            if ($newLayout !== $this->ReadPropertyString('TechnicalLayout')) {
                IPS_SetProperty($this->InstanceID, 'TechnicalLayout', $newLayout);
                IPS_ApplyChanges($this->InstanceID);
                $this->ReloadForm();
            } else {
                $this->PushState();
            }

            return;
        }

        if ($Ident === 'ToggleDisplayMode') {
            $newMode = ((string) $Value === 'house') ? 'house' : 'flow';

            if ($newMode !== $this->ReadPropertyString('DisplayMode')) {
                IPS_SetProperty($this->InstanceID, 'DisplayMode', $newMode);
                IPS_ApplyChanges($this->InstanceID);
                $this->ReloadForm();
            } else {
                $this->PushState();
            }

            return;
        }
    }

    public function ResetHouseColors(): void
    {
        // Originalfarben der eingebetteten Haus-SVG wiederherstellen.
        $defaults = [
            'HouseColorFacade'        => 2107187,  // #202733
            'HouseColorRoof'          => 1646636,  // #19202c
            'HouseColorRoofSecondary' => 1712685,  // #1a222d
            'HouseColorWindows'       => 16772536, // #ffedb8
            'HouseColorSolarPanels'   => 10393218, // #9e9682
            'HouseColorInverter'      => 857372,   // #0d151c
            'HouseColorCar'           => 790808,   // #0c1118
            'HouseColorCarDetails'    => 1448741,  // #161b25
            'HouseColorBattery'       => 858148,   // #0d1824
            'HouseColorBatteryAccent' => 6868216,  // #68ccf8
        ];

        foreach ($defaults as $property => $value) {
            IPS_SetProperty($this->InstanceID, $property, $value);
        }

        IPS_ApplyChanges($this->InstanceID);
        $this->ReloadForm();
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
        background: transparent;
        overflow: hidden;

        /* Grafik und Bedienung bewusst trennen:
           oben nur die Visualisierung, unten der klickbare Umschalter. */
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    #scale-host {
        width: 100%;
        flex: 1 1 auto;
        min-height: 0;
        height: auto;
        overflow: hidden;
        position: relative;
    }

    #display-mode-bar {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 6px 4px 0;
        min-height: 32px;
    }

    #display-mode-button,
    #technical-layout-button {
        appearance: none;
        border: 1px solid var(--w-border);
        border-radius: 7px;
        width: 34px;
        height: 30px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--w-surface);
        color: var(--w-text2);
        font: inherit;
        font-size: 19px;
        line-height: 1;
        font-weight: 600;
        cursor: pointer;
        outline: none;
    }

    #display-mode-button:hover,
    #technical-layout-button:hover {
        color: var(--w-text);
        border-color: var(--w-text2);
    }

    #display-mode-button:active,
    #technical-layout-button:active {
        transform: translateY(1px);
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
    #stage > .node { display: none !important; }
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
    .node .val { color: #ffffff !important; font-weight: 500; }
    .node .sub { color: #ffffff !important; }

    /* Energiefluss: Leistungswerte bewusst deutlich größer als Zusatzwerte. */
    #stage .node .val {
        font-size: 23px !important;
        line-height: 1.05;
        white-space: nowrap;
    }

    /* Hauswert minimal größer als die übrigen Hauptwerte. */
    #n-haus .val {
        font-size: 25px !important;
        line-height: 1.05;
        white-space: nowrap;
    }

    /* Icons/Werte innerhalb der Kreise sollen unabhängig vom Symcon-Theme
       immer gut lesbar sein. */
    .node .body,
    .node .body * {
        color: #ffffff !important;
    }

    /* Klassischer Energiefluss: alle Energie-/kWh-Zeilen exakt gleich groß.
       SOC und andere Zusatzwerte bleiben davon unabhängig. */
    .node .energy-sub {
        font-size: 13px !important;
        line-height: 1.15 !important;
        white-space: nowrap;
    }

    #stage .energy-sub {
        font-size: 13px !important;
        line-height: 1.15;
        white-space: nowrap;
    }

    .lbl {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        color: var(--w-text2);
        white-space: nowrap;
    }
    .lbl.top { bottom: 100%; margin-bottom: 15px; }
    .lbl.bot { top: 100%; margin-top: 14px; }

    #n-haus .lbl {
        top: 16px !important;
        bottom: auto !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        margin: 0 !important;
        width: 90px;
        text-align: center;
        font-size: 14px !important;
        line-height: 1;
    }

    #n-haus .body {
        padding-top: 16px;
    }

    /* Neue technische Energieflussansicht. Die Hauptwerte bleiben groß;
       zusätzliche Informationen stehen in dynamischen Technikfeldern. */
    #technical-dashboard {
        position: absolute;
        inset: 0;
        z-index: 10;
        display: grid;
        grid-template-columns: 1fr 1.15fr 1fr;
        grid-template-rows: auto 1fr auto;
        grid-template-areas:
            "pv summary grid"
            "pv center grid"
            "battery consumers wallbox";
        gap: 10px;
        padding: 8px;
        box-sizing: border-box;
        color: var(--w-text);
    }

    #technical-dashboard.compact {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto auto 1fr;
        grid-template-areas:
            "summary summary"
            "pv grid"
            "battery consumers";
    }

    #sunsynk-host { position:absolute; inset:0; overflow:hidden; display:flex; align-items:center; justify-content:center; }
    #sunsynk-host sunsynk-power-flow-card { display:block; width:100%; height:100%; --ha-card-background:transparent; --card-background-color:transparent; --primary-text-color:var(--w-text); --secondary-text-color:var(--w-text2); }
    #sunsynk-loading, #sunsynk-error { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:var(--w-text2); font-size:16px; padding:20px; text-align:center; box-sizing:border-box; }
    #sunsynk-error { display:none; color:#ef5350; }
    .tech-card {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--w-border);
        border-radius: 12px;
        background: color-mix(in srgb, var(--w-surface) 92%, transparent);
        box-shadow: 0 4px 14px rgba(0,0,0,.10);
        padding: 10px 12px;
        box-sizing: border-box;
    }

    .tech-card-title {
        display: flex;
        align-items: center;
        gap: 7px;
        color: var(--w-text2);
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 7px;
        white-space: nowrap;
    }

    .tech-main {
        font-size: 30px;
        line-height: 1.05;
        font-weight: 750;
        white-space: nowrap;
    }

    .tech-sub {
        margin-top: 4px;
        color: var(--w-text2);
        font-size: 14px;
        line-height: 1.25;
    }

    .tech-list { display: grid; gap: 5px; }
    .tech-row {
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        gap: 8px;
        align-items: baseline;
        padding: 5px 0;
        border-top: 1px solid var(--w-border);
    }
    .tech-row:first-child { border-top: 0; }
    .tech-row-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 14px;
        font-weight: 650;
    }
    .tech-row-value {
        white-space: nowrap;
        font-size: 17px;
        font-weight: 750;
    }
    .tech-row-detail {
        grid-column: 1 / -1;
        margin-top: -3px;
        color: var(--w-text2);
        font-size: 12px;
        line-height: 1.15;
    }

    #tech-summary { grid-area: summary; }
    #tech-pv { grid-area: pv; border-color: color-mix(in srgb, var(--ef-solar) 55%, var(--w-border)); }
    #tech-grid { grid-area: grid; }
    #tech-center { grid-area: center; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; }
    #tech-battery { grid-area: battery; }
    #tech-consumers { grid-area: consumers; }
    #tech-wallbox { grid-area: wallbox; }

    .tech-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0,1fr));
        gap: 7px;
    }
    .tech-summary-cell {
        min-width: 0;
        border-left: 3px solid var(--w-border);
        padding-left: 7px;
    }
    .tech-summary-label { color: var(--w-text2); font-size: 11px; white-space: nowrap; }
    .tech-summary-value { font-size: 18px; font-weight: 750; white-space: nowrap; }

    .tech-flow-hub {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        border: 7px solid var(--ef-consumer);
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        background: var(--w-surface);
        box-shadow: 0 0 0 6px color-mix(in srgb, var(--w-line) 50%, transparent);
    }
    .tech-flow-hub i { font-size: 32px; margin-bottom: 4px; }
    .tech-flow-hub .tech-main { font-size: 28px; }

    .tech-direction { font-size: 14px; font-weight: 700; margin-top: 5px; }
    .tech-empty { color: var(--w-text2); font-size: 14px; padding: 8px 0; }

    #technical-dashboard.compact #tech-center,
    #technical-dashboard.compact #tech-wallbox { display: none !important; }
    #technical-dashboard.compact .tech-card { padding: 9px 11px; }
    #technical-dashboard.compact .tech-main { font-size: 27px; }
    #technical-dashboard.compact .tech-row-value { font-size: 16px; }

    @media (max-width: 600px) {
        #technical-dashboard { gap: 7px; padding: 5px; }
        .tech-card { padding: 9px 10px; border-radius: 10px; }
        .tech-card-title { font-size: 17px; }
        .tech-main { font-size: 31px; }
        .tech-row-name { font-size: 16px; }
        .tech-row-value { font-size: 19px; }
        .tech-row-detail { font-size: 14px; }
        .tech-summary-label { font-size: 13px; }
        .tech-summary-value { font-size: 21px; }
    }

/* Hausansicht – LordGuenni/power-flow-card */
    #house-stage {
        position: relative;
        width: 900px;
        height: 640px;
        display: __HOUSE_DISPLAY__;
        overflow: hidden;
        box-sizing: border-box;

        /* Hausansicht freigestellt: der Hintergrund kommt von Symcon. */
        border: none;
        border-radius: 0;
        background: transparent;
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
        --energy-solar-color: var(--ef-solar, #ffd54f);
        --energy-grid-consumption-color: var(--ef-grid-import, #ef5350);
        --energy-grid-return-color: var(--ef-grid-export, #66bb6a);
        --energy-battery-charge-color: var(--ef-battery-charge, #64b5f6);
        --energy-battery-discharge-color: var(--ef-battery-discharge, #29b6f6);
        --energy-car-color: var(--ef-consumer, #2fa98f);
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

        /* Nur die Infobox selbst bleibt leicht abgesetzt. Die Fläche rund
           um Haus und Leitungen bleibt vollständig transparent. */
        background: var(--w-surface);
        background: color-mix(in srgb, var(--w-surface) 88%, transparent);
        border: 1px solid var(--w-border);
        box-shadow: 0 5px 18px rgba(0,0,0,.16);
        color: var(--w-text);
        line-height: 1.25;
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
    }

    .pfc-info .title {
        color: var(--w-text2);
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
        color: var(--w-text2);
        font-size: 13px;
        margin-top: 2px;
        line-height: 1.2;
        white-space: nowrap;
    }

    #pfc-info-solar {
        left: 56%;
        top: 2%;
        border-color: rgba(239,160,32,.36);
    }

    #pfc-info-home {
        right: 7%;
        top: 20%;
        border-color: rgba(77,159,255,.36);
    }

    #pfc-info-battery {
        left: 55%;
        bottom: 19%;
        transform: translateX(-50%);
        border-color: var(--ef-battery-discharge, #29b6f6);
    }

    #pfc-info-wallbox {
        left: 19%;
        top: 39%;
        bottom: auto;
        border-color: var(--ef-consumer, #2fa98f);
    }

    /* Netz sitzt unten direkt bei den beiden Import-/Export-Leitungen. */
    #pfc-info-grid {
        right: 4%;
        bottom: 1%;
        min-width: 165px;
        border-color: rgba(255,255,255,.16);
    }

    #pfc-grid-import {
        color: var(--ef-grid-import, #ef5350);
    }

    #pfc-grid-export {
        color: var(--ef-grid-export, #66bb6a);
    }

    #pfc-battery-main.discharge {
        color: var(--ef-battery-discharge, #29b6f6);
    }

    #pfc-battery-main.charge {
        color: var(--ef-battery-charge, #64b5f6);
    }

    #pfc-solar-main {
        color: var(--ef-solar, #ffd54f);
    }

    #pfc-home-main {
        color: #4d9fff;
    }

    #pfc-wallbox-main {
        color: var(--ef-consumer, #2fa98f);
    }

    /* Responsive Infokacheln:
       Die eigentliche Hausgrafik wird weiterhin ausschließlich über fit()
       proportional skaliert. Nur die Schrift der Infokacheln wird je nach
       verfügbarer Breite größer dargestellt. */

    /* Ab 601 px überall dieselbe etwas größere Standardschrift.
       Positionen und vollständige Detailinfos bleiben unverändert. */
    @media (min-width: 601px) {
        .pfc-info .title {
            font-size: 12px;
        }

        .pfc-info .main {
            font-size: 21px;
        }

        .pfc-info .sub {
            font-size: 13px;
        }
    }

    /* Kompakte Handyansicht */
    @media (max-width: 600px) {
        #eflow {
            padding: 2px 1px;
            border-radius: 0;
        }

        #display-mode-bar {
            justify-content: center;
            padding-top: 3px;
            min-height: 28px;
        }

        /* Nur auf dem Handy gelten die speziellen Kachelpositionen. */
        #pfc-info-solar {
            left: 45%;
            top: 1%;
        }

        #pfc-info-home {
            right: 7%;
            top: 1%;
        }

        #pfc-info-battery {
            left: 55%;
            bottom: 16%;
        }

        #pfc-info-grid {
            right: 2.5%;
            bottom: 0%;
        }

        #display-mode-button,
        #technical-layout-button {
            width: 34px;
            height: 30px;
            padding: 0;
            font-size: 19px;
        }

        .pfc-info {
            width: max-content;
            max-width: 230px;
        }

        .pfc-info .title {
            font-size: 20px;
        }

        .pfc-info .main {
            font-size: 30px;
        }

        .pfc-info .sub {
            font-size: 18px;
        }
    }


</style>
<script src="/icons.js"></script>
<script>
    // Muss VOR dem Import der originalen Sunsynk-Datei existieren.
    // Die Original-Card registriert sich beim Laden über window.customCards.push(...).
    window.customCards = window.customCards || [];
</script>
<script type="module"
        src="/user/Energiefluss/vendor/power-flow-card.js">
</script>

<div id="eflow">
    <div id="scale-host">
        <div id="scale-root">
            <div id="wrap">
                <div id="fit">

                    <!-- Klassische Energieflussansicht -->
                    <div id="stage">
                        <div id="technical-dashboard" class="full">
                            <div id="sunsynk-host">
                                <div id="sunsynk-loading">Technische Energieflusskarte wird geladen …</div>
                                <div id="sunsynk-error"></div>
                            </div>
                        </div>
                        <svg id="svg" style="display:none" width="1080" height="640" viewBox="0 0 1080 640" aria-hidden="true">
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
                                <div id="pfc-home-energy" class="sub"></div>
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
            </div>
        </div>
    </div>

    <div id="display-mode-bar">
        <button id="technical-layout-button" type="button" title="Technikansicht verdichten" aria-label="Technikansicht verdichten">▦</button>
        <button id="display-mode-button" type="button" title="Ansicht wechseln" aria-label="Ansicht wechseln">⇄</button>
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

    // Zentrale Farbdefinition – entspricht der ursprünglichen
    // power-flow-card YAML-Konfiguration.
    const AC = {
        solar: '#ffd54f',
        grid: '#ef5350',
        room: '#2FA98F',
        batt: '#64b5f6',
        import: '#ef5350',
        export: '#66bb6a',
        discharge: '#29b6f6',
        charge: '#64b5f6',
        wallbox: '#2FA98F',
        home: '#4d9fff'
    };

    let flowSpeedFactor = 1.0;

    const NSc = 'http://www.w3.org/2000/svg';
    const RR = 38;
    const COL0 = 530;
    const COLW = 120;

    // ---------- klassische Ansicht ----------
    const MAIN = {
        netz: { x: 110, y: 350, r: 51, ic: 'bolt', icc: AC.grid, lab: 'Netz', lp: 'bot' },
        haus: { x: 360, y: 350, r: 58, ic: 'house', icc: 'var(--w-text)', lab: 'Haus', lp: 'bot', ring: true }
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

    function fmtKwh(value) {
        const n = Number(value);
        if (!Number.isFinite(n)) {
            return '';
        }

        return n.toLocaleString('de-DE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' kWh';
    }

    function addNode(id, n, cls) {
        const el = document.createElement('div');
        el.className = 'node' + (cls ? ' ' + cls : '');
        el.id = 'n-' + id;

        const border = n.ring ? 'transparent' : n.icc;
        const isz = n.r < 44 ? 20 : 25;

        el.style.cssText =
            `left:${n.x}px;top:${n.y}px;width:${n.r * 2}px;height:${n.r * 2}px;border:3px solid ${border};`;

        el.innerHTML =
            `<div class="lbl ${n.lp}" style="font-size:${n.r < 44 ? 17 : 21}px">${n.lab}</div>` +
            `<i class="fa-solid fa-${n.ic}" style="font-size:${isz}px;color:${n.icc}"></i>` +
            `<div class="body" id="body-${id}" style="font-size:${n.r < 44 ? 17 : 21}px"></div>`;

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
        document.querySelectorAll('.pv-node, .battery-node, .wallbox-node').forEach(e => e.remove());

        Object.keys(lineEl)
            .filter(k => k.startsWith('pv') || k.startsWith('bat') || k === 'wallbox')
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
                    r: 49,
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
                    ? `<div class="sub energy-sub">${pv.energy}</div>`
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
                    r: 47,
                    ic: 'battery-half',
                    icc: batColor,
                    lab: bat.name || ('Batterie ' + (i + 1)),
                    lp: 'bot',
                    ring: true
                },
                'battery-node'
            );

            const batteryEnergyLines = [];
            if (bat.dischargeEnergyText) {
                batteryEnergyLines.push(`→ ${bat.dischargeEnergyText}`);
            }
            if (bat.chargeEnergyText) {
                batteryEnergyLines.push(`← ${bat.chargeEnergyText}`);
            }

            document.getElementById('body-bat' + i).innerHTML =
                `<div class="sub" style="font-size:15px">${Math.round(bat.soc || 0)}%</div>` +
                `<div class="val" style="color:${batColor}">${fmt(Math.abs(bat.value || 0))}</div>` +
                (batteryEnergyLines.length
                    ? `<div class="sub energy-sub">${batteryEnergyLines.join('<br>')}</div>`
                    : '');

            addEdge(
                'bat' + i,
                `M${p.x},506 L${p.x},455 L360,455 L360,402`,
                batColor
            );
        });
    }

    function buildWallbox(wallbox, hasWallbox, groupCount) {
        if (!hasWallbox) {
            return;
        }

        const withConsumers = groupCount > 0;

        // Mit Verbrauchern ist die Wallbox einfach Verbraucher Nr. 1:
        // erste normale Position oben. Nur alleine sitzt sie mittig.
        const p = withConsumers
            ? gpos(0)
            : { x: 532, y: 350, lp: 'bot' };

        const radius = withConsumers ? 44 : 47;

        addNode(
            'wallbox',
            {
                x: p.x,
                y: p.y,
                r: radius,
                ic: 'charging-station',
                icc: AC.room,
                lab: wallbox.name || 'Wallbox',
                lp: p.lp,
                ring: true
            },
            'wallbox-node'
        );

        let inner = '';

        if (wallbox.hasSoc) {
            inner += `<div class="sub" style="font-size:11px;color:${AC.room}">${wallbox.socText}</div>`;
        }

        inner += `<div class="val" style="color:${AC.room}">${fmt(Math.max(wallbox.value || 0, 0))}</div>`;

        if (wallbox.energy) {
            inner += `<div class="sub energy-sub">${wallbox.energy}</div>`;
        }

        const body = document.getElementById('body-wallbox');
        if (body) {
            body.innerHTML = inner;
        }

        if (withConsumers) {
            const endY = p.lp === 'top' ? p.y + radius : p.y - radius;
            addEdge(
                'wallbox',
                `M412,350 L${p.x},350 L${p.x},${endY}`,
                AC.room
            );
        } else {
            addEdge(
                'wallbox',
                `M412,350 L${p.x - radius},350`,
                AC.room
            );
        }
    }

    function buildGroups(list, hasWallbox = false) {
        document.querySelectorAll('.grp-node').forEach(e => e.remove());

        Object.keys(lineEl)
            .filter(k => k.startsWith('grp'))
            .forEach(k => {
                lineEl[k].remove();
                dotEl[k].forEach(d => d.remove());
                delete lineEl[k];
                delete dotEl[k];
            });

        // Wallbox ist Verbraucher Nr. 1. Der erste konfigurierte
        // Verbraucher kommt dadurch direkt auf Position 2 (unten).
        const offset = hasWallbox && list.length > 0 ? 1 : 0;

        list.forEach((g, i) => {
            const p = gpos(i + offset);

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
                inner += `<div class="sub energy-sub">${g.daily}</div>`;
            }
            document.getElementById('body-r' + i).innerHTML = inner;

            const endY = p.lp === 'top' ? p.y + RR : p.y - RR;
            addEdge('grp' + i, `M412,350 L${p.x},350 L${p.x},${endY}`, AC.room);
        });
    }

    function wallboxSocPercent(wallbox) {
        if (!wallbox || !wallbox.hasSoc || !wallbox.socText) {
            return null;
        }

        const raw = String(wallbox.socText).replace(',', '.');

        // Für den Ring nur eine Prozentzahl bestimmen.
        // Die Textanzeige selbst bleibt unverändert.
        const percentMatch = raw.match(/([-+]?\d+(?:\.\d+)?)\s*%/);
        if (percentMatch) {
            return Math.max(0, Math.min(100, Number(percentMatch[1])));
        }

        const numbers = raw.match(/[-+]?\d+(?:\.\d+)?/g);
        if (!numbers || !numbers.length) {
            return null;
        }

        let value = Number(numbers[numbers.length - 1]);
        if (!Number.isFinite(value)) {
            return null;
        }

        if (value > 0 && value < 1) {
            value *= 100;
        }

        return Math.max(0, Math.min(100, value));
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

    function updateRings(segs, batteries, wallbox, hasWallbox, groupCount) {
        ringG.innerHTML = '';

        track(360, 350, 66);
        const tot = segs.reduce((a, s) => a + s[1], 0) || 1;
        let acc = 0;

        segs.forEach(([col, v]) => {
            if (v > 0) {
                arc(360, 350, 66, col, v / tot, acc / tot);
                acc += v;
            }
        });

        batteries.forEach((bat, i) => {
            const p = batteryPos(i);
            const batColor = (bat.value || 0) >= 0 ? AC.discharge : AC.charge;
            track(p.x, p.y, 53);
            arc(p.x, p.y, 53, batColor, Math.max(0, Math.min(100, bat.soc || 0)) / 100, 0);
        });

        if (hasWallbox) {
            const withConsumers = groupCount > 0;
            const p = withConsumers ? gpos(0) : { x: 532, y: 350 };
            const ringRadius = withConsumers ? 50 : 53;
            const soc = wallboxSocPercent(wallbox);

            track(p.x, p.y, ringRadius);

            if (soc !== null) {
                arc(p.x, p.y, ringRadius, AC.room, soc / 100, 0);
            }
        }
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
        // Vollständige Konfiguration der unveränderten Original-Card.
        // Entspricht der früheren Home-Assistant-YAML, nur mit den
        // virtuellen Symcon-Sensoren aus updatePowerFlowCard().
        return {
            name: 'Home Energy Flow',
            threshold: 10,

            dynamic_speed_enabled: true,
            min_flow_speed: 5 / flowSpeedFactor,
            max_flow_speed: 1 / flowSpeedFactor,
            min_power_threshold: 100,
            max_power_threshold: 10000,

            solar_line_color: AC.solar,
            grid_import_line_color: AC.import,
            grid_export_line_color: AC.export,
            battery_charge_line_color: AC.charge,
            battery_discharge_line_color: AC.discharge,
            ev_line_color: AC.room,

            entities: {
                solar_power: 'sensor.symcon_solar',
                grid_import_power: 'sensor.symcon_grid_import',
                grid_export_power: 'sensor.symcon_grid_export',
                ev_charge_power: 'sensor.symcon_ev',
                battery_charge_power: 'sensor.symcon_battery_charge',
                battery_discharge_power: 'sensor.symcon_battery_discharge'
            },

            solar_descriptor_enabled: false,
            solar_descriptor_label: 'Solar',
            solar_descriptor_entity: 'sensor.symcon_solar',

            grid_descriptor_enabled: false,
            grid_descriptor_label: 'Grid',
            grid_descriptor_entity: 'sensor.symcon_grid',

            battery_descriptor_enabled: false,
            battery_descriptor_label: 'Battery',
            battery_descriptor_entity: 'sensor.symcon_battery_soc',

            ev_descriptor_enabled: false,
            ev_descriptor_label: 'EV',
            ev_descriptor_entity: 'sensor.symcon_ev',

            home_descriptor_enabled: false,
            home_descriptor_label: 'Home',
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

                #svg-overlay,
                #svg-container-bg {
                    background: transparent !important;
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


    function applyPfcBatteryFlowColor(batteryTotal) {
        if (!pfcCard || !pfcCard.shadowRoot) {
            return;
        }

        const batteryContainer =
            pfcCard.shadowRoot.getElementById('svg-container-battery');

        if (!batteryContainer) {
            return;
        }

        // Modulkonvention:
        // positiv = Entladen Richtung Haus
        // negativ = Laden Richtung Batterie
        const isDischarge = batteryTotal > 0;
        const isCharge = batteryTotal < 0;

        const color = isDischarge
            ? AC.discharge
            : (isCharge ? AC.charge : AC.charge);

        // Die Upstream-Card verwendet für den Batteriepfad intern nicht
        // zuverlässig unterschiedliche Klassen. Deshalb setzen wir Klasse
        // und Farbe passend zur tatsächlichen Flussrichtung explizit.
        batteryContainer
            .querySelectorAll('.anim-line')
            .forEach(line => {
                line.classList.toggle('bat-discharge', isDischarge);
                line.classList.toggle('bat-charge', !isDischarge);

                line.style.setProperty('stroke', color, 'important');
                line.style.setProperty('color', color, 'important');
            });

        batteryContainer.style.setProperty(
            '--pfc-battery-charge-color',
            AC.charge
        );
        batteryContainer.style.setProperty(
            '--pfc-battery-discharge-color',
            AC.discharge
        );
    }

    function applyPfcBackgroundColors(d) {
        if (!pfcCard || !pfcCard.shadowRoot || !d || !d.houseColors) {
            return;
        }

        const container = pfcCard.shadowRoot.getElementById('svg-container-bg');
        const bgSvg = container ? container.querySelector('svg') : null;

        if (!bgSvg) {
            return;
        }

        const c = d.houseColors;

        const setFill = (selector, color) => {
            if (!color) return;
            bgSvg.querySelectorAll(selector).forEach(el => {
                el.style.setProperty('fill', color, 'important');
            });
        };

        // Haus/Fassade
        setFill('#house path', c.facade);

        // Das Original-SVG besitzt zwei unterschiedlich gefärbte Dachpfade.
        const roofPaths = bgSvg.querySelectorAll('#roof path');
        if (roofPaths[0] && c.roof) {
            roofPaths[0].style.setProperty('fill', c.roof, 'important');
        }
        if (roofPaths[1] && c.roofSecondary) {
            roofPaths[1].style.setProperty('fill', c.roofSecondary, 'important');
        }

        // Fenster, PV-Module und Wechselrichter
        setFill('#windows path', c.windows);
        setFill('#solar path', c.solarPanels);
        setFill('#inverter path', c.inverter);

        // Fahrzeug: erster Pfad = Grundkörper, restliche Pfade = Details.
        const carPaths = bgSvg.querySelectorAll('#car path');
        if (carPaths[0] && c.car) {
            carPaths[0].style.setProperty('fill', c.car, 'important');
        }
        carPaths.forEach((el, index) => {
            if (index > 0 && c.carDetails) {
                el.style.setProperty('fill', c.carDetails, 'important');
            }
        });

        // Batteriespeicher: Gehäuse + farbiger Akzent.
        const batteryPaths = bgSvg.querySelectorAll('#battery path');
        if (batteryPaths[0] && c.battery) {
            batteryPaths[0].style.setProperty('fill', c.battery, 'important');
        }
        if (batteryPaths[1] && c.batteryAccent) {
            batteryPaths[1].style.setProperty('fill', c.batteryAccent, 'important');
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

        // Wallbox: den echten EV-Container verwenden.
        // Nur dieser Container wird vom Upstream updateFlow() über
        // entities.ev_charge_power / sensor.symcon_ev angesteuert.
        const wallboxContainer =
            pfcCard.shadowRoot.getElementById('svg-container-ev');

        if (batteryContainer) {
            batteryContainer.style.display = hasBattery ? '' : 'none';
        }

        if (wallboxContainer) {
            wallboxContainer.style.display = hasWallbox ? '' : 'none';
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

                // Die Hintergrund-SVG wird vom Upstream-Code asynchron geladen.
                // Falls bereits Daten vorhanden sind, Farben sofort anwenden.
                if (pfcPendingData && pfcPendingData[0]) {
                    applyPfcBackgroundColors(pfcPendingData[0]);
                }

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

    function alignHomeInfoToSolarBottom() {
        const homeInfo = document.getElementById('pfc-info-home');
        if (!homeInfo) {
            return;
        }

        // Die Sonderposition gilt ausschließlich für die Handyansicht.
        if (!window.matchMedia('(max-width: 600px)').matches) {
            homeInfo.style.top = '';
            return;
        }

        const solarInfo = document.getElementById('pfc-info-solar');
        if (!solarInfo) {
            return;
        }

        const top =
            solarInfo.offsetTop +
            solarInfo.offsetHeight -
            homeInfo.offsetHeight +
            14;

        homeInfo.style.top = Math.max(0, top) + 'px';
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
            if (window.matchMedia('(max-width: 600px)').matches) {
                const totalEnergy = pvs.reduce((sum, pv) => {
                    const value = Number(pv.energyValue);
                    return sum + (Number.isFinite(value) ? value : 0);
                }, 0);

                const hasAnyEnergy = pvs.some(pv => !!pv.hasEnergy);

                pvSub.textContent = hasAnyEnergy
                    ? fmtKwh(totalEnergy)
                    : '';
            } else {
                pvSub.innerHTML = pvs.map((pv, i) => {
                    const name = pv.name || ('PV ' + (i + 1));
                    const energy = pv.energy ? ` · ${pv.energy}` : '';
                    return `${name}: ${fmt(pv.value || 0)}${energy}`;
                }).join('<br>');
            }
        }

        // Haus
        const homeMain = document.getElementById('pfc-home-main');
        const homeEnergy = document.getElementById('pfc-home-energy');

        if (homeMain) {
            homeMain.textContent = fmt(haus);
        }

        if (homeEnergy) {
            homeEnergy.textContent = d.houseEnergyAvailable
                ? fmtKwh(d.houseEnergy)
                : '';
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

            if (batteryInfo) {
                batteryInfo.style.borderColor = discharge
                    ? AC.discharge
                    : AC.charge;
            }

            if (batteryTitle) {
                batteryTitle.textContent = batteries.length === 1
                    ? (mainBat.name || 'Batterie')
                    : 'Batterien';
            }

            if (batteryMain) {
                batteryMain.textContent = fmt(Math.abs(batteryTotal));
                batteryMain.classList.toggle('discharge', discharge);
                batteryMain.classList.toggle('charge', !discharge);
                batteryMain.style.color = discharge ? AC.discharge : AC.charge;
            }

            if (batteryInfo) {
                batteryInfo.style.borderColor = discharge ? AC.discharge : AC.charge;
            }

            if (batterySub) {
                if (window.matchMedia('(max-width: 600px)').matches) {
                    const mainSoc = Number(mainBat.soc || 0);
                    batterySub.textContent =
                        `${Math.round(Number.isFinite(mainSoc) ? mainSoc : 0)} % SOC`;
                } else {
                    batterySub.innerHTML = batteries.map((bat, i) => {
                        const name = bat.name || ('Batterie ' + (i + 1));
                        const mode = (bat.value || 0) >= 0 ? 'Entladen' : 'Laden';
                        const energyParts = [];

                        if (bat.dischargeEnergyText) {
                            energyParts.push(`→ ${bat.dischargeEnergyText}`);
                        }
                        if (bat.chargeEnergyText) {
                            energyParts.push(`← ${bat.chargeEnergyText}`);
                        }

                        const energy = energyParts.length
                            ? `<br>${energyParts.join(' · ')}`
                            : '';

                        return `${name}: ${Math.round(bat.soc || 0)} % · ${mode}${energy}`;
                    }).join('<br>');
                }
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
                if (window.matchMedia('(max-width: 600px)').matches) {
                    wallboxSub.textContent = wallbox.hasSoc
                        ? wallbox.socText
                        : '';
                } else {
                    const details = [];
                    if (wallbox.hasSoc) {
                        details.push(wallbox.socText);
                    }
                    if (wallbox.energy) {
                        details.push(wallbox.energy);
                    }
                    wallboxSub.textContent = details.join(' · ');
                }
            }
        }

        // Netz:
        // Bis 600px nur EIN Gesamtwert (Saldo) anzeigen.
        // positiv = Netzbezug -> rot
        // negativ = Einspeisung -> grün
        // Ab 601px bleiben Bezug und Einspeisung wie bisher getrennt sichtbar.
        const gridImport = Math.max(grid, 0);
        const gridExport = Math.max(-grid, 0);

        const gridInfo = document.getElementById('pfc-info-grid');
        const gridImportEl = document.getElementById('pfc-grid-import');
        const gridExportEl = document.getElementById('pfc-grid-export');
        const gridSub = document.getElementById('pfc-grid-sub');

        const compactGrid = window.matchMedia('(max-width: 600px)').matches;

        if (compactGrid) {
            const isExport = grid < 0;
            const gridColor = isExport ? AC.export : AC.import;

            // Oben nur die gesamte aktuelle Netzleistung.
            if (gridImportEl) {
                gridImportEl.textContent = fmt(Math.abs(grid));
                gridImportEl.style.color = gridColor;
                gridImportEl.style.display = '';
            }

            if (gridExportEl) {
                gridExportEl.textContent = '';
                gridExportEl.style.display = 'none';
            }

            // Darunter wieder wie früher in kleiner Schrift:
            // Bezug / Einspeisung mit den vorhandenen Energiewerten.
            if (gridSub) {
                const energy = [];
                if (d.gridImportEnergy) {
                    energy.push('→ ' + d.gridImportEnergy);
                }
                if (d.gridExportEnergy) {
                    energy.push('← ' + d.gridExportEnergy);
                }
                gridSub.innerHTML = energy.join('<br>');
                gridSub.style.display = '';
            }

            if (gridInfo) {
                gridInfo.style.borderColor = gridColor;
            }
        } else {
            if (gridImportEl) {
                gridImportEl.textContent = `→ ${fmt(gridImport)}`;
                gridImportEl.style.color = AC.import;
                gridImportEl.style.display = '';
            }

            if (gridExportEl) {
                gridExportEl.textContent = `← ${fmt(gridExport)}`;
                gridExportEl.style.color = AC.export;
                gridExportEl.style.display = '';
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
                gridSub.style.display = '';
            }

            if (gridInfo) {
                gridInfo.style.borderColor = 'rgba(255,255,255,.16)';
            }
        }

        requestAnimationFrame(alignHomeInfoToSolarBottom);
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

        // Die power-flow-card liest die Leitungsfarben aus ihrer Config.
        // Deshalb nach einer Farbänderung die Config mit den aktuellen
        // AC-Farben erneut setzen, bevor die neuen Zustände übergeben werden.
        if (typeof pfcCard.setConfig === 'function') {
            pfcCard.setConfig(createPfcConfig());
        }

        pfcCard.hass = { states };

        applyPfcBatteryFlowColor(batteryTotal);
        updatePfcInfoCards(d, grid, haus, pvs, batteries, wallbox);

        installPfcShadowOverrides(pfcCard);
        applyPfcBackgroundColors(d);
        applyPfcOptionalLayers(d, batteries, wallbox);

        // Upstream updateFlow() wird bereits vom hass-Setter ausgelöst.
        // Für den Fall eines noch laufenden Initialisierungszyklus nochmals
        // nach dem nächsten Frame anwenden.
        requestAnimationFrame(() => {
            if (pfcCard && pfcCard.isInitialized && typeof pfcCard.updateFlow === 'function') {
                pfcCard.updateFlow();
                applyPfcBatteryFlowColor(batteryTotal);
                applyPfcBackgroundColors(d);
                applyPfcOptionalLayers(d, batteries, wallbox);
                    }
        });
    }


    function techRow(name, value, detail = '', color = '') {
        const style = color ? ` style="color:${color}"` : '';
        return `<div class="tech-row">
            <div class="tech-row-name">${escapeHtml(name)}</div>
            <div class="tech-row-value"${style}>${escapeHtml(value)}</div>
            ${detail ? `<div class="tech-row-detail">${detail}</div>` : ''}
        </div>`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    // ---------- Originale Sunsynk Power Flow Card ----------
    let sunsynkCard = null;
    let sunsynkInitPromise = null;
    let sunsynkPending = null;

    function ssState(value, unit = 'W') {
        const n = Number(value);
        return { state: String(Number.isFinite(n) ? n : 0), attributes: { unit_of_measurement: unit } };
    }

    let sunsynkModulePromise = null;

    function ensureHaCompatibility() {
        window.customCards = window.customCards || [];

        // Home-Assistant-Icon als kleiner Fallback. Die Sunsynk-Hauptgrafik
        // besteht aus eigenem SVG; dieser Fallback betrifft Zusatzicons.
        if (!customElements.get('ha-icon')) {
            customElements.define('ha-icon', class extends HTMLElement {
                static get observedAttributes() { return ['icon']; }
                connectedCallback() { this.render(); }
                attributeChangedCallback() { this.render(); }
                render() {
                    const raw = this.getAttribute('icon') || 'mdi:circle-outline';
                    const icon = raw.split(':').pop() || 'circle-outline';
                    const faMap = {
                        'home': 'house', 'home-outline': 'house',
                        'car': 'car', 'car-electric': 'car',
                        'ev-station': 'charging-station',
                        'battery': 'battery-half', 'battery-medium': 'battery-half',
                        'solar-power': 'solar-panel', 'solar-panel': 'solar-panel',
                        'transmission-tower': 'tower-broadcast',
                        'power-plug': 'plug', 'flash': 'bolt'
                    };
                    const fa = faMap[icon] || 'circle';
                    this.innerHTML = `<i class="fa-solid fa-${fa}" aria-hidden="true"></i>`;
                }
            });
        }
    }

    async function loadOriginalSunsynkModule() {
        ensureHaCompatibility();

        if (customElements.get('sunsynk-power-flow-card')) {
            return;
        }

        if (!sunsynkModulePromise) {
            const moduleUrl = '/user/Energiefluss/vendor/sunsynk-power-flow-card.js';
            sunsynkModulePromise = import(moduleUrl).catch(err => {
                sunsynkModulePromise = null;
                throw new Error(`Originale Sunsynk-JS konnte nicht importiert werden: ${err.message}`);
            });
        }

        await sunsynkModulePromise;

        if (!customElements.get('sunsynk-power-flow-card')) {
            throw new Error('Die JS-Datei wurde geladen, hat aber sunsynk-power-flow-card nicht registriert.');
        }
    }

    function createSunsynkConfig(d, pvs, batteries, wallbox, groups) {
        const style = ['compact', 'lite', 'full'].includes(currentTechnicalLayout) ? currentTechnicalLayout : 'lite';
        const compact = style === 'compact';
        const full = style === 'full';
        const pvCount = Math.max(1, Math.min(6, pvs.length || 1));
        const essentialLoads = [];
        if (d.hasWallbox) essentialLoads.push({name: wallbox?.name || 'Wallbox', value: Number(wallbox?.value || 0), dailyValue: Number(wallbox?.energyValue || 0), hasDaily: !!wallbox?.hasEnergy, icon: 'mdi:ev-station', isWallbox: true});
        groups.forEach(group => essentialLoads.push({...group, icon: group.icon || 'mdi:power-plug', isWallbox: false}));
        const shownLoads = essentialLoads.slice(0, full ? 6 : 3);
        const essentialCount = shownLoads.length;
        const showEnergyDetails = !compact;
        return {
            cardstyle: style,
            wide: full,
            large_font: true,
            show_solar: pvs.length > 0,
            show_battery: batteries.length > 0,
            show_grid: true,
            decimal_places: 0,
            decimal_places_energy: 2,
            dynamic_line_width: true,
            max_line_width: 5,
            min_line_width: 2,
            inverter: { modern: true, model: 'goodwe', colour: AC.home, autarky: 'power', auto_scale: false, label_autarky: 'Autarkie', label_ratio: 'Eigenverbrauch' },
            solar: {
                colour: AC.solar, show_daily: showEnergyDetails && pvs.some(pv => pv.hasEnergy), mppts: pvCount,
                animation_speed: Math.max(1, Math.round(9 / flowSpeedFactor)), max_power: 12000,
                auto_scale: false, display_mode: 1,
                pv1_name: pvs[0]?.name || 'PV 1', pv2_name: pvs[1]?.name || 'PV 2',
                pv3_name: pvs[2]?.name || 'PV 3', pv4_name: pvs[3]?.name || 'PV 4',
                pv5_name: pvs[4]?.name || 'PV 5', pv6_name: pvs[5]?.name || 'PV 6'
            },
            battery: {
                count: Math.min(2, Math.max(1, batteries.length)),
                // Die Originalkarte behandelt 0 als "nicht angegeben" und bricht ab.
                // 0.0001 bleibt für die Validierung gültig, wird bei 0 Dezimalstellen aber als 0 % angezeigt.
                shutdown_soc: Math.max(0.0001, Math.min(100, Number(batteries[0]?.maxDischargeSoc ?? 0))),
                soc_end_of_charge: 100,
                hide_soc: false,
                soc_decimal_places: 0,
                colour: AC.discharge, charge_colour: AC.charge, show_daily: showEnergyDetails && batteries.some(b => b.hasChargeEnergy || b.hasDischargeEnergy),
                animation_speed: Math.max(1, Math.round(6 / flowSpeedFactor)), max_power: 10000,
                auto_scale: false, dynamic_colour: true, linear_gradient: true, animate: true, show_absolute: true
            },
            battery2: {
                shutdown_soc: Math.max(0.0001, Math.min(100, Number(batteries[1]?.maxDischargeSoc ?? 0))),
                soc_end_of_charge: 100,
                hide_soc: false,
                soc_decimal_places: 0,
                colour: AC.discharge,
                charge_colour: AC.charge, show_absolute: true, auto_scale: false,
                dynamic_colour: true, linear_gradient: true, animate: true
            },
            load: {
                colour: AC.home, off_colour: '#9e9e9e', dynamic_colour: false, dynamic_icon: false,
                show_daily: showEnergyDetails && groups.some(g => g.hasDaily), show_aux: false, show_daily_aux: false,
                animation_speed: Math.max(1, Math.round(4 / flowSpeedFactor)), max_power: 12000,
                auto_scale: false, additional_loads: essentialCount, aux_loads: 0,
                essential_name: 'Hausverbrauch',
                load1_name: shownLoads[0]?.name || '', load2_name: shownLoads[1]?.name || '',
                load3_name: shownLoads[2]?.name || '', load4_name: shownLoads[3]?.name || '',
                load5_name: shownLoads[4]?.name || '', load6_name: shownLoads[5]?.name || '',
                load1_icon: shownLoads[0]?.icon || 'mdi:power-plug', load2_icon: shownLoads[1]?.icon || 'mdi:power-plug',
                load3_icon: shownLoads[2]?.icon || 'mdi:power-plug', load4_icon: shownLoads[3]?.icon || 'mdi:power-plug',
                load5_icon: shownLoads[4]?.icon || 'mdi:power-plug', load6_icon: shownLoads[5]?.icon || 'mdi:power-plug'
            },
            grid: {
                colour: AC.import, export_colour: AC.export, grid_name: 'Netz',
                show_daily_buy: showEnergyDetails && !!d.gridImportEnergyValueAvailable, show_daily_sell: showEnergyDetails && !!d.gridExportEnergyValueAvailable,
                show_nonessential: false, additional_loads: 0,
                animation_speed: Math.max(1, Math.round(8 / flowSpeedFactor)), max_power: 12000,
                auto_scale: false, show_absolute: true
            },
            entities: {
                battery_soc_184: 'sensor.symcon_battery_soc', battery_power_190: 'sensor.symcon_battery_power',
                battery_current_191: 'sensor.symcon_battery_current', battery2_soc_184: 'sensor.symcon_battery2_soc',
                battery2_power_190: 'sensor.symcon_battery2_power', pv1_power_186: 'sensor.symcon_pv1',
                pv2_power_187: 'sensor.symcon_pv2', pv3_power_188: 'sensor.symcon_pv3',
                pv4_power_189: 'sensor.symcon_pv4', pv5_power: 'sensor.symcon_pv5', pv6_power: 'sensor.symcon_pv6',
                grid_ct_power_172: 'sensor.symcon_grid', grid_connected_status_194: 'sensor.symcon_grid_status',
                essential_power: 'sensor.symcon_home', inverter_power_175: 'sensor.symcon_inverter',
                aux_power_166: 'sensor.symcon_wallbox', day_aux_energy: 'sensor.symcon_wallbox_energy',
                day_pv_energy_108: 'sensor.symcon_pv_energy',
                day_grid_import_76: 'sensor.symcon_grid_import_energy',
                day_grid_export_77: 'sensor.symcon_grid_export_energy',
                day_load_energy_84: 'sensor.symcon_load_energy',
                day_battery_charge_70: 'sensor.symcon_battery_charge_energy',
                day_battery_discharge_71: 'sensor.symcon_battery_discharge_energy',
                day_battery2_charge_70: 'sensor.symcon_battery2_charge_energy',
                day_battery2_discharge_71: 'sensor.symcon_battery2_discharge_energy',
                essential_load1: 'sensor.symcon_branch1', essential_load1_extra: 'sensor.symcon_branch1_daily',
                essential_load2: 'sensor.symcon_branch2', essential_load2_extra: 'sensor.symcon_branch2_daily',
                essential_load3: 'sensor.symcon_branch3', essential_load3_extra: 'sensor.symcon_branch3_daily',
                essential_load4: 'sensor.symcon_branch4', essential_load4_extra: 'sensor.symcon_branch4_daily',
                essential_load5: 'sensor.symcon_branch5', essential_load5_extra: 'sensor.symcon_branch5_daily',
                essential_load6: 'sensor.symcon_branch6', essential_load6_extra: 'sensor.symcon_branch6_daily'
            }
        };
    }

    function createSunsynkHass(d, grid, haus, pvs, batteries, wallbox, groups) {
        const bat1 = batteries[0] || { value: 0, soc: 0 };
        const bat2 = batteries[1] || { value: 0, soc: 0 };
        const pvTotal = pvs.reduce((sum, pv) => sum + Number(pv.value || 0), 0);
        const pvEnergyTotal = pvs.reduce((sum, pv) => sum + Number(pv.energyValue || 0), 0);
        const loadEnergyTotal = groups.reduce((sum, group) => sum + Number(group.dailyValue || 0), 0);
        const states = {
            'sensor.symcon_battery_soc': ssState(bat1.soc || 0, '%'),
            'sensor.symcon_battery_power': ssState(-(bat1.value || 0), 'W'),
            'sensor.symcon_battery_current': ssState(0, 'A'),
            'sensor.symcon_battery2_soc': ssState(bat2.soc || 0, '%'),
            'sensor.symcon_battery2_power': ssState(-(bat2.value || 0), 'W'),
            'sensor.symcon_grid': ssState(grid || 0, 'W'),
            'sensor.symcon_grid_status': { state: 'on-grid', attributes: {} },
            'sensor.symcon_home': ssState(haus || 0, 'W'),
            'sensor.symcon_inverter': ssState(pvTotal, 'W'),
            'sensor.symcon_wallbox': ssState(wallbox?.value || 0, 'W'),
            'sensor.symcon_wallbox_energy': ssState(wallbox?.energyValue || 0, 'kWh'),
            'sensor.symcon_pv_energy': ssState(pvEnergyTotal, 'kWh'),
            'sensor.symcon_grid_import_energy': ssState(d.gridImportEnergyValue || 0, 'kWh'),
            'sensor.symcon_grid_export_energy': ssState(d.gridExportEnergyValue || 0, 'kWh'),
            'sensor.symcon_load_energy': ssState(loadEnergyTotal, 'kWh'),
            'sensor.symcon_battery_charge_energy': ssState(bat1.chargeEnergy || 0, 'kWh'),
            'sensor.symcon_battery_discharge_energy': ssState(bat1.dischargeEnergy || 0, 'kWh'),
            'sensor.symcon_battery2_charge_energy': ssState(bat2.chargeEnergy || 0, 'kWh'),
            'sensor.symcon_battery2_discharge_energy': ssState(bat2.dischargeEnergy || 0, 'kWh')
        };
        for (let i = 0; i < 6; i++) {
            states[`sensor.symcon_pv${i + 1}`] = ssState(pvs[i]?.value || 0, 'W');
        }
        const branchLoads = [];
        if (d.hasWallbox) branchLoads.push({value: Number(wallbox?.value || 0), dailyValue: Number(wallbox?.energyValue || 0)});
        groups.forEach(group => branchLoads.push(group));
        for (let i = 0; i < 6; i++) {
            states[`sensor.symcon_branch${i + 1}`] = ssState(branchLoads[i]?.value || 0, 'W');
            states[`sensor.symcon_branch${i + 1}_daily`] = ssState(branchLoads[i]?.dailyValue || 0, 'kWh');
        }
        return {
            states,
            locale: { language: 'de', number_format: 'comma_decimal' },
            language: 'de',
            config: { unit_system: { length: 'km', mass: 'kg', temperature: '°C', volume: 'L' } },
            themes: { darkMode: document.documentElement.getAttribute('data-theme') === 'dark' },
            localize: key => key,
            callService: () => Promise.resolve(),
            navigate: () => {}
        };
    }

    async function applySunsynkViewOverrides(card, styleName) {
        const compact = styleName === 'compact';
        if (!card) return;

        try {
            if (card.updateComplete) {
                await card.updateComplete;
            }
            await new Promise(resolve => requestAnimationFrame(resolve));
        } catch (_) {}

        const root = card.shadowRoot;
        if (!root) return;

        let style = root.getElementById('symcon-sunsynk-overrides');
        if (!style) {
            style = document.createElement('style');
            style.id = 'symcon-sunsynk-overrides';
            root.appendChild(style);
        }

        style.textContent = `
            :host {
                width: 100% !important;
                height: 100% !important;
            }

            .card,
            ha-card {
                width: 100% !important;
                height: 100% !important;
                max-width: none !important;
                background: transparent !important;
                box-shadow: none !important;
                border: 0 !important;
            }

            /* Alle Leistungswerte und Beschriftungen besser lesbar. */
            .st3 { font-size: ${compact ? 13 : 11}px !important; }
            .st4 { font-size: ${compact ? 18 : 16}px !important; }
            .st10 { font-size: ${compact ? 21 : 18}px !important; }
            .st13 { font-size: ${compact ? 29 : 25}px !important; }
            .st14 { font-size: ${compact ? 17 : 14}px !important; }
            .remaining-energy { font-size: ${compact ? 13 : 10}px !important; }

            /* Der mittlere/rechte Hauptwert ist der gesamte Hausverbrauch. */
            text[id*="essential_power"],
            [data-entity*="essential_power"] text {
                font-size: ${compact ? 25 : 21}px !important;
                font-weight: 700 !important;
            }

            /* In Kompakt keine versteckten Zusatzlasten oder kWh-Blöcke
               durch nachträgliches Rendern wieder sichtbar werden lassen. */
            ${compact ? `
                [class*="additional-load"],
                [class*="aux-load"],
                [class*="nonessential-load"],
                [class*="non-essential-load"] {
                    display: none !important;
                }
            ` : ''}
        `;
    }

    async function ensureSunsynkCard(d, grid, haus, pvs, batteries, wallbox, groups) {
        if (sunsynkCard) return sunsynkCard;
        if (sunsynkInitPromise) return sunsynkInitPromise;
        sunsynkInitPromise = (async () => {
            await loadOriginalSunsynkModule();
            const host = document.getElementById('sunsynk-host');
            const card = document.createElement('sunsynk-power-flow-card');

            // Wie in Lovelace: zuerst Konfiguration und hass setzen,
            // anschließend das Element in den DOM einhängen.
            card.setConfig(createSunsynkConfig(d, pvs, batteries, wallbox, groups));
            card.hass = createSunsynkHass(
                d,
                grid,
                haus,
                pvs,
                batteries,
                wallbox,
                groups
            );
            host.appendChild(card);
            sunsynkCard = card;
            await applySunsynkViewOverrides(card, currentTechnicalLayout);
            document.getElementById('sunsynk-loading').style.display = 'none';
            if (sunsynkPending) {
                const args = sunsynkPending; sunsynkPending = null; renderTechnicalView(...args);
            }
            return card;
        })().catch(err => {
            console.error('Sunsynk-Karte:', err);
            const loading = document.getElementById('sunsynk-loading');
            const error = document.getElementById('sunsynk-error');
            if (loading) loading.style.display = 'none';
            if (error) { error.style.display = 'flex'; error.textContent = 'Sunsynk-Karte konnte nicht geladen werden: ' + err.message; }
            throw err;
        });
        return sunsynkInitPromise;
    }

    function renderTechnicalView(d, grid, haus, pvs, batteries, wallbox, groups) {
        currentTechnicalLayout = ['compact', 'lite', 'full'].includes(d.technicalLayout) ? d.technicalLayout : 'lite';
        const layoutButton = document.getElementById('technical-layout-button');
        if (layoutButton) {
            layoutButton.textContent = ({compact: 'C', lite: 'L', full: 'F'})[currentTechnicalLayout] || 'L';
            layoutButton.title = `Sunsynk-Ansicht: ${currentTechnicalLayout}`;
        }
        if (!sunsynkCard) {
            sunsynkPending = [d, grid, haus, pvs, batteries, wallbox, groups];
            ensureSunsynkCard(d, grid, haus, pvs, batteries, wallbox, groups).catch(() => {});
            return;
        }
        sunsynkCard.setConfig(createSunsynkConfig(d, pvs, batteries, wallbox, groups));
        sunsynkCard.hass = createSunsynkHass(d, grid, haus, pvs, batteries, wallbox, groups);
        applySunsynkViewOverrides(sunsynkCard, currentTechnicalLayout);
    }

    function buildHouseView(d, grid, haus, pvs, batteries, wallbox) {
        updatePowerFlowCard(d, grid, haus, pvs, batteries, wallbox);
    }

    let currentDisplayMode = '__INITIAL_DISPLAY_MODE__';
    let currentTechnicalLayout = 'lite';

    function updateDisplayModeButton() {
        const button = document.getElementById('display-mode-button');
        if (!button) {
            return;
        }

        button.textContent = '⇄';
        button.title = currentDisplayMode === 'house'
            ? 'Energiefluss anzeigen'
            : 'Hausansicht anzeigen';
        button.setAttribute(
            'aria-label',
            currentDisplayMode === 'house'
                ? 'Energiefluss anzeigen'
                : 'Hausansicht anzeigen'
        );
    }

    function applyDisplayMode(mode) {
        const house = mode === 'house';
        currentDisplayMode = house ? 'house' : 'flow';

        if (stage) stage.style.display = house ? 'none' : 'block';
        if (houseStage) houseStage.style.display = house ? 'block' : 'none';
        const layoutButton = document.getElementById('technical-layout-button');
        if (layoutButton) layoutButton.style.display = house ? 'none' : 'inline-flex';

        updateDisplayModeButton();

        // Beide Ansichten bleiben auf dem von IP-Symcon
        // vorgegebenen Hintergrund.
        const eflow = document.getElementById('eflow');
        if (eflow) {
            eflow.style.background = 'transparent';
        }
    }

    const displayModeButton = document.getElementById('display-mode-button');
    if (displayModeButton) {
        displayModeButton.addEventListener('click', function () {
            const newMode = currentDisplayMode === 'house' ? 'flow' : 'house';
            requestAction('ToggleDisplayMode', newMode);
        });
    }

    const technicalLayoutButton = document.getElementById('technical-layout-button');
    if (technicalLayoutButton) {
        technicalLayoutButton.addEventListener('click', function () {
            const order = ['compact', 'lite', 'full'];
            const newLayout = order[(Math.max(0, order.indexOf(currentTechnicalLayout)) + 1) % order.length];
            requestAction('ToggleTechnicalLayout', newLayout);
        });
    }

    updateDisplayModeButton();

    // ---------- Layout ----------
    let layoutWidth = 540;

    function updateLayout(groupCount, pvCount, batteryCount, showRightPanel, mode = 'flow', hasWallbox = false) {
        const fitEl = document.getElementById('fit');
        const wrapEl = document.getElementById('wrap');
        const rootEl = document.getElementById('scale-root');

        let graphWidth = mode === 'house' ? 900 : 540;

        if (mode !== 'house') {
            const effectiveCount =
                groupCount + ((hasWallbox && groupCount > 0) ? 1 : 0);
            const columns = Math.ceil(effectiveCount / 2);

            if (columns > 0) {
                graphWidth = Math.max(graphWidth, 650 + ((columns - 1) * COLW));
            }

            if (hasWallbox && groupCount === 0) {
                graphWidth = Math.max(graphWidth, 592);
            }

            graphWidth = Math.min(graphWidth, 1080);
        }

        layoutWidth = graphWidth;

        fitEl.style.width = graphWidth + 'px';
        fitEl.style.flexBasis = graphWidth + 'px';

        wrapEl.style.width = layoutWidth + 'px';
        rootEl.style.width = layoutWidth + 'px';

        wrapEl.style.gap = '0px';

        fit();
    }

    // ---------- Zustand ----------
    function applyConfiguredColors(d) {
        if (d && d.colors) {
            Object.assign(AC, d.colors);
            AC.grid = AC.import;
            AC.batt = AC.charge;
            AC.room = d.colors.room || AC.room;
            AC.wallbox = d.colors.wallbox || AC.wallbox;
            AC.home = d.colors.home || AC.home;
        }

        const speedPercent = Number(d && d.flowSpeedPercent);
        flowSpeedFactor = Number.isFinite(speedPercent)
            ? Math.max(0.25, Math.min(3.0, speedPercent / 100))
            : 1.0;

        document.documentElement.style.setProperty('--ef-solar', AC.solar);
        document.documentElement.style.setProperty('--ef-grid-import', AC.import);
        document.documentElement.style.setProperty('--ef-grid-export', AC.export);
        document.documentElement.style.setProperty('--ef-battery-charge', AC.charge);
        document.documentElement.style.setProperty('--ef-battery-discharge', AC.discharge);
        document.documentElement.style.setProperty('--ef-wallbox', AC.wallbox);
        document.documentElement.style.setProperty('--ef-consumer', AC.room);

        const solarMain = document.getElementById('pfc-solar-main');
        const solarInfo = document.getElementById('pfc-info-solar');
        const gridImport = document.getElementById('pfc-grid-import');
        const gridExport = document.getElementById('pfc-grid-export');
        const gridInfo = document.getElementById('pfc-info-grid');
        const wallboxMain = document.getElementById('pfc-wallbox-main');
        const wallboxInfo = document.getElementById('pfc-info-wallbox');

        if (solarMain) solarMain.style.color = AC.solar;
        if (solarInfo) solarInfo.style.borderColor = AC.solar;
        if (gridImport) gridImport.style.color = AC.import;
        if (gridExport) gridExport.style.color = AC.export;
        if (gridInfo) gridInfo.style.borderColor = AC.import;
        if (wallboxMain) wallboxMain.style.color = AC.wallbox;
        if (wallboxInfo) wallboxInfo.style.borderColor = AC.wallbox;
    }

    function setState(d) {
        applyConfiguredColors(d);
        const grid = d.grid || 0;
        const imp = Math.max(grid, 0);

        const pvs = d.pvs || [];
        const batteries = d.batteries || [];
        const groups = d.groups || [];
        const wallbox = d.wallbox || { name: 'Wallbox', value: 0, energy: '', socText: '', hasSoc: false };

        const pvTotal = pvs.reduce((sum, pv) => sum + (pv.value || 0), 0);
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);

        // Netzbezug positiv, Rücklieferung negativ.
        const haus = Math.max(pvTotal + batteryTotal + grid, 0);

        // Neue technische Ansicht.
        currentTechnicalLayout = ['compact', 'lite', 'full'].includes(d.technicalLayout) ? d.technicalLayout : 'lite';
        renderTechnicalView(d, grid, haus, pvs, batteries, wallbox, groups);

        // Alte SVG-Struktur bleibt intern nur für Abwärtskompatibilität erhalten.
        clearDynamicSources();
        buildPVs(pvs);
        buildBatteries(batteries);
        buildGroups(groups, !!d.hasWallbox);
        buildWallbox(wallbox, !!d.hasWallbox, groups.length);

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
                ? `<div class="sub energy-sub" style="color:${AC.import}">&rarr; ${d.gridImportEnergy}</div>`
                : '') +
            (d.gridExportEnergy
                ? `<div class="sub energy-sub" style="color:${AC.export}">&larr; ${d.gridExportEnergy}</div>`
                : '');

        document.getElementById('body-haus').innerHTML =
            `<div class="val" style="font-size:17px">${fmt(haus)}</div>` +
            (d.houseEnergyAvailable
                ? `<div class="sub energy-sub">${fmtKwh(d.houseEnergy)}</div>`
                : '');

        updateRings(
            [
                [AC.solar, Math.max(pvTotal, 0)],
                [AC.discharge, Math.max(batteryTotal, 0)],
                [AC.import, imp]
            ],
            batteries,
            wallbox,
            !!d.hasWallbox,
            groups.length
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

        if (d.hasWallbox) {
            edgeState['wallbox'] = {
                w: Math.max(wallbox.value || 0, 0),
                rev: false
            };
        }

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

        updateLayout(
            groups.length,
            pvs.length,
            batteries.length,
            false,
            (d.displayMode || 'flow') === 'house' ? 'house' : 'flow',
            !!d.hasWallbox
        );
    }

    let lastStateData = null;
    let lastCompactLayout = window.matchMedia('(max-width: 600px)').matches;

    function handleMessage(data) {
        const d = typeof data === 'string' ? JSON.parse(data) : data;

        if (d && d.command === 'reloadHtml') {
            window.location.reload();
            return;
        }

        lastStateData = d;
        setState(d);
    }

    function refreshResponsiveState() {
        const compactNow = window.matchMedia('(max-width: 600px)').matches;

        // Nur bei einem echten Wechsel zwischen den zwei vorhandenen
        // Darstellungen neu aufbauen. So gibt es beim Ziehen keine alten
        // Misch-/Zwischenanzeigen mehr.
        if (compactNow !== lastCompactLayout) {
            lastCompactLayout = compactNow;

            if (lastStateData) {
                setState(lastStateData);
            }
        }
    }

    // ---------- Animation ----------
    let last = performance.now();

    function powerSpeed(w) {
        const power = Math.max(0, Math.abs(w || 0));
        if (power <= 0) {
            return 0;
        }

        const speed = (0.040 + (Math.sqrt(power) * 0.00285)) * flowSpeedFactor;
        return Math.min(speed, 0.96);
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

        // Hausansicht:
        // Die komplette 900x640-Zeichenfläche inklusive Infoboxen wird
        // proportional anhand der verfügbaren BREITE skaliert.
        //
        // Dadurch:
        // - nutzt das Haus die gesamte HTML-Breite,
        // - das Seitenverhältnis bleibt unverändert,
        // - die Grafik wird nicht verzerrt,
        // - alle Infoboxen bleiben an exakt derselben relativen Position,
        //   weil sie innerhalb desselben house-stage mitskaliert werden.
        //
        // Klassische Ansicht:
        // Weiterhin wie bisher vollständig in Breite UND Höhe einpassen.
        const houseMode = houseStage && houseStage.style.display !== 'none';

        const scaleX = availableWidth / baseWidth;
        const scaleY = availableHeight / baseHeight;

        // Proportional skalieren.
        //
        // Energiefluss:
        // Die gesamte verfügbare BREITE wird verwendet. Dadurch werden
        // Kreise, Schrift, Icons, Linien und Abstände immer gemeinsam
        // und gleichmäßig skaliert. Es gibt keinen zusätzlichen Zoomfaktor,
        // der später zu Abschneiden oder ungenutzter Breite führen kann.
        //
        // Hausansicht:
        // Weiterhin vollständig in Breite und Höhe einpassen.
        // Größtmögliche proportionale Skalierung ohne Abschneiden.
        // Wenn die Höhe reicht, wird die volle Breite genutzt. Ist die Höhe
        // der begrenzende Faktor, wird entsprechend kleiner skaliert.
        const scale = Math.min(scaleX, scaleY);

        root.style.transform = `scale(${scale})`;

        const scaledWidth = baseWidth * scale;
        const scaledHeight = baseHeight * scale;

        // Energiefluss nutzt die maximale Breite exakt aus.
        // Hausansicht bleibt bei eventuell vorhandener Restbreite zentriert.
        root.style.left = `${Math.max(0, (availableWidth - scaledWidth) / 2)}px`;

        // Auf schmalen Handyansichten die proportional skalierte Grafik
        // nach unten ausrichten. Dadurch landet der untere Rand der internen
        // 640px-Zeichenfläche tatsächlich am unteren Rand des verfügbaren
        // Grafikbereichs, statt durch vertikale Zentrierung Leerraum darunter
        // zu erzeugen. Ab 601px bleibt die bisherige Zentrierung erhalten.
        root.style.top = `${Math.max(0, (availableHeight - scaledHeight) / 2)}px`;
    }

    const scaleHost = document.getElementById('scale-host');
    if (scaleHost) {
        new ResizeObserver(() => {
            fit();
            refreshResponsiveState();
        }).observe(scaleHost);
    }

    window.addEventListener('resize', () => {
        fit();
        refreshResponsiveState();
        requestAnimationFrame(alignHomeInfoToSolarBottom);
    });
    window.addEventListener('load', () => {
        fit();
        requestAnimationFrame(alignHomeInfoToSolarBottom);
    });

    fit();
    requestAnimationFrame(frame);
</script>
HTML;

        return str_replace(
            ['__FLOW_DISPLAY__', '__HOUSE_DISPLAY__', '__INITIAL_DISPLAY_MODE__'],
            [$flowDisplay, $houseDisplay, $showHouse ? 'house' : 'flow'],
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
         *   assets/vendor/sunsynk-power-flow-card.js
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
                    'Visualisierung: Web-Verzeichnis konnte nicht erstellt werden: ' . $targetDir,
                    KL_ERROR
                );
                return;
            }
        }

        $assets = [
            'power-flow-card.js',
            'lit-core.min.js',
            'sunsynk-power-flow-card.js',
        ];

        foreach ($assets as $asset) {
            $source = $sourceDir . DIRECTORY_SEPARATOR . $asset;
            $target = $targetDir . DIRECTORY_SEPARATOR . $asset;

            if (!is_file($source)) {
                $this->LogMessage(
                    'Visualisierung: Datei fehlt im Modulbaum: ' . $source,
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


    private function ResolveVariableID(int $objectID): int
    {
        if ($objectID <= 0 || !IPS_ObjectExists($objectID)) {
            return 0;
        }

        // Direkte Variable.
        if (IPS_VariableExists($objectID)) {
            return $objectID;
        }

        // Links auflösen. Mehrere Link-Ebenen werden ebenfalls unterstützt.
        $visited = [];
        $currentID = $objectID;

        for ($i = 0; $i < 10; $i++) {
            if ($currentID <= 0 || isset($visited[$currentID]) || !IPS_ObjectExists($currentID)) {
                return 0;
            }

            $visited[$currentID] = true;

            if (IPS_VariableExists($currentID)) {
                return $currentID;
            }

            if (!IPS_LinkExists($currentID)) {
                return 0;
            }

            $link = IPS_GetLink($currentID);
            $currentID = (int) ($link['TargetID'] ?? 0);
        }

        return 0;
    }

    private function ReadWallboxSoC(): array
    {
        $selectedID = $this->ReadPropertyInteger('WallboxSoC');
        $id = $this->ResolveVariableID($selectedID);

        if ($id <= 0 || !IPS_VariableExists($id)) {
            return [
                'hasSoc' => false,
                'socText' => '',
            ];
        }

        $value = GetValue($id);

        if (is_bool($value)) {
            $text = $value ? 'true' : 'false';
        } else {
            $text = (string) $value;
        }

        return [
            'hasSoc' => true,
            'socText' => $text,
        ];
    }

    private function CollectVariableIDs(): array
    {
        $ids = [];

        foreach ([
            'L1',
            'GridExportPower',
            'GridImportEnergy',
            'GridExportEnergy',
            'WallboxPower',
            'WallboxEnergy',
        ] as $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($id > 0) {
                $ids[] = $id;
            }

        $wallboxSoCVariableID = $this->ResolveVariableID(
            $this->ReadPropertyInteger('WallboxSoC')
        );
        if ($wallboxSoCVariableID > 0) {
            $ids[] = $wallboxSoCVariableID;
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
                foreach ([
                    'VariableID',
                    'EnergyVariableID',
                    'ChargeEnergyVariableID',
                    'DischargeEnergyVariableID',
                    'SoCVariableID'
                ] as $key) {
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

        return array_values(array_unique($ids));
    }

    private function ColorToHex(int $color): string
    {
        return sprintf('#%06x', $color & 0xFFFFFF);
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

                $hasEnergy = $energyVariableID > 0 && IPS_VariableExists($energyVariableID);

                $pvs[] = [
                    'name'        => trim((string) ($source['Name'] ?? '')) !== ''
                        ? (string) $source['Name']
                        : 'PV ' . (count($pvs) + 1),
                    'value'       => (float) GetValue($variableID),
                    'energy'      => $hasEnergy ? GetValueFormatted($energyVariableID) : '',
                    'energyValue' => $hasEnergy ? (float) GetValue($energyVariableID) : 0.0,
                    'hasEnergy'   => $hasEnergy,
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

                $chargeEnergyVariableID = (int) ($source['ChargeEnergyVariableID'] ?? 0);
                $dischargeEnergyVariableID = (int) ($source['DischargeEnergyVariableID'] ?? 0);
                $socVariableID = (int) ($source['SoCVariableID'] ?? 0);

                $value = (float) GetValue($variableID);
                if ((bool) ($source['InvertFlow'] ?? false)) {
                    $value *= -1;
                }

                $hasChargeEnergy =
                    $chargeEnergyVariableID > 0 &&
                    IPS_VariableExists($chargeEnergyVariableID);

                $hasDischargeEnergy =
                    $dischargeEnergyVariableID > 0 &&
                    IPS_VariableExists($dischargeEnergyVariableID);

                $batteries[] = [
                    'name'                 => trim((string) ($source['Name'] ?? '')) !== ''
                        ? (string) $source['Name']
                        : 'Batterie ' . (count($batteries) + 1),
                    'value'                => $value,
                    'soc'                  => ($socVariableID > 0 && IPS_VariableExists($socVariableID))
                        ? (float) GetValue($socVariableID)
                        : 0.0,
                    'maxDischargeSoc'      => max(
                        0,
                        min(100, (int) ($source['MaxDischargeSoC'] ?? 0))
                    ),
                    'chargeEnergy'         => $hasChargeEnergy
                        ? (float) GetValue($chargeEnergyVariableID)
                        : 0.0,
                    'chargeEnergyText'     => $hasChargeEnergy
                        ? GetValueFormatted($chargeEnergyVariableID)
                        : '',
                    'hasChargeEnergy'      => $hasChargeEnergy,
                    'dischargeEnergy'      => $hasDischargeEnergy
                        ? (float) GetValue($dischargeEnergyVariableID)
                        : 0.0,
                    'dischargeEnergyText'  => $hasDischargeEnergy
                        ? GetValueFormatted($dischargeEnergyVariableID)
                        : '',
                    'hasDischargeEnergy'   => $hasDischargeEnergy,
                ];
            }
        }

        // Wallbox.
        $wallboxSoC = $this->ReadWallboxSoC();

        $wallbox = [
            'name'    => $this->ReadPropertyString('WallboxName'),
            'value'   => $this->ReadVar('WallboxPower'),
            'energy'  => $this->ReadVarFormatted('WallboxEnergy'),
            'energyValue' => $this->ReadVar('WallboxEnergy'),
            'hasEnergy' => ($this->ReadPropertyInteger('WallboxEnergy') > 0 && IPS_VariableExists($this->ReadPropertyInteger('WallboxEnergy'))),
            'socText' => $wallboxSoC['socText'],
            'hasSoc'  => $wallboxSoC['hasSoc'],
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
                $hasDaily = ($dailyVariableID > 0 && IPS_VariableExists($dailyVariableID));
                $daily = $hasDaily ? GetValueFormatted($dailyVariableID) : '';
                $dailyValue = $hasDaily ? (float) GetValue($dailyVariableID) : 0.0;

                $groups[] = [
                    'name'  => (string) ($group['Name'] ?? ''),
                    'icon'  => (string) ($group['Icon'] ?? 'plug'),
                    'value' => $value,
                    'daily' => $daily,
                    'dailyValue' => $dailyValue,
                    'hasDaily' => $hasDaily,
                ];
            }
        }


        // Energiebilanz des Hauses in kWh:
        // PV + Netzbezug - Einspeisung + Batterieentladung - Batterieladung.
        $gridImportEnergyID = $this->ReadPropertyInteger('GridImportEnergy');
        $gridExportEnergyID = $this->ReadPropertyInteger('GridExportEnergy');

        $hasGridImportEnergy =
            $gridImportEnergyID > 0 &&
            IPS_VariableExists($gridImportEnergyID);

        $hasGridExportEnergy =
            $gridExportEnergyID > 0 &&
            IPS_VariableExists($gridExportEnergyID);

        $pvEnergyTotal = 0.0;
        $hasPvEnergy = count($pvs) > 0;
        foreach ($pvs as $pv) {
            if (!($pv['hasEnergy'] ?? false)) {
                $hasPvEnergy = false;
                break;
            }
            $pvEnergyTotal += (float) ($pv['energyValue'] ?? 0.0);
        }

        $batteryChargeEnergyTotal = 0.0;
        $batteryDischargeEnergyTotal = 0.0;
        $hasBatteryEnergy = true;

        foreach ($batteries as $battery) {
            if (
                !($battery['hasChargeEnergy'] ?? false) ||
                !($battery['hasDischargeEnergy'] ?? false)
            ) {
                $hasBatteryEnergy = false;
                break;
            }

            $batteryChargeEnergyTotal += (float) ($battery['chargeEnergy'] ?? 0.0);
            $batteryDischargeEnergyTotal += (float) ($battery['dischargeEnergy'] ?? 0.0);
        }

        // Ohne Batterie ist dieser Teil der Bilanz automatisch vollständig.
        if (count($batteries) === 0) {
            $hasBatteryEnergy = true;
        }

        $houseEnergyAvailable =
            $hasPvEnergy &&
            $hasGridImportEnergy &&
            $hasGridExportEnergy &&
            $hasBatteryEnergy;

        $houseEnergy = 0.0;
        if ($houseEnergyAvailable) {
            $houseEnergy =
                $pvEnergyTotal +
                (float) GetValue($gridImportEnergyID) -
                (float) GetValue($gridExportEnergyID) +
                $batteryDischargeEnergyTotal -
                $batteryChargeEnergyTotal;
        }

        return [
            'displayMode'      => $this->ReadPropertyString('DisplayMode'),
            'technicalLayout'  => $this->ReadPropertyString('TechnicalLayout'),
            'pvs'              => $pvs,
            'batteries'        => $batteries,
            'grid'             => $grid,
            'gridImportEnergy' => $this->ReadVarFormatted('GridImportEnergy'),
            'gridExportEnergy' => $this->ReadVarFormatted('GridExportEnergy'),
            'gridImportEnergyValue' => $hasGridImportEnergy ? (float) GetValue($gridImportEnergyID) : 0.0,
            'gridExportEnergyValue' => $hasGridExportEnergy ? (float) GetValue($gridExportEnergyID) : 0.0,
            'gridImportEnergyValueAvailable' => $hasGridImportEnergy,
            'gridExportEnergyValueAvailable' => $hasGridExportEnergy,
            'houseEnergy'       => $houseEnergy,
            'houseEnergyAvailable' => $houseEnergyAvailable,
            'wallbox'          => $wallbox,
            'hasWallbox'       => (
                $this->ReadPropertyInteger('WallboxPower') > 0
                && IPS_VariableExists($this->ReadPropertyInteger('WallboxPower'))
            ),
            'groups'           => $groups,
            'flowSpeedPercent' => $this->ReadPropertyInteger('FlowSpeedPercent'),
            'houseColors'      => [
                'facade'           => $this->ColorToHex($this->ReadPropertyInteger('HouseColorFacade')),
                'roof'             => $this->ColorToHex($this->ReadPropertyInteger('HouseColorRoof')),
                'roofSecondary'    => $this->ColorToHex($this->ReadPropertyInteger('HouseColorRoofSecondary')),
                'windows'          => $this->ColorToHex($this->ReadPropertyInteger('HouseColorWindows')),
                'solarPanels'      => $this->ColorToHex($this->ReadPropertyInteger('HouseColorSolarPanels')),
                'inverter'         => $this->ColorToHex($this->ReadPropertyInteger('HouseColorInverter')),
                'car'              => $this->ColorToHex($this->ReadPropertyInteger('HouseColorCar')),
                'carDetails'       => $this->ColorToHex($this->ReadPropertyInteger('HouseColorCarDetails')),
                'battery'          => $this->ColorToHex($this->ReadPropertyInteger('HouseColorBattery')),
                'batteryAccent'    => $this->ColorToHex($this->ReadPropertyInteger('HouseColorBatteryAccent')),
            ],
            'colors'           => [
                'solar'     => $this->ColorToHex($this->ReadPropertyInteger('ColorSolar')),
                'import'    => $this->ColorToHex($this->ReadPropertyInteger('ColorGridImport')),
                'export'    => $this->ColorToHex($this->ReadPropertyInteger('ColorGridExport')),
                'charge'    => $this->ColorToHex($this->ReadPropertyInteger('ColorBatteryCharge')),
                'discharge' => $this->ColorToHex($this->ReadPropertyInteger('ColorBatteryDischarge')),
                'home'      => $this->ColorToHex($this->ReadPropertyInteger('ColorHouseLoad')),
                'wallbox'   => $this->ColorToHex($this->ReadPropertyInteger('ColorWallbox')),
                'room'      => $this->ColorToHex($this->ReadPropertyInteger('ColorConsumers')),
            ],
        ];
    }
}
