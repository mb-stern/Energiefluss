<?php

/*
* ## Third-Party Components

* Komponente | Lizenz | Verwendung |
* ------------|---------|------------|
* Sunsynk Power Flow Card | Apache License 2.0 | Technische Energieflussdarstellung |
* Power Flow Card (LordGuenni) | MIT | Hausgrafik |
* Lit (Google LLC) | BSD-3-Clause | Web Components Framework |
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
        $this->RegisterPropertyString('Inverters', '[]');
        $this->RegisterPropertyInteger('OutsideTemperature', 0);
        $this->RegisterPropertyInteger('SolarForecastRemaining', 0);

        // Netz.
        $this->RegisterPropertyInteger('L1', 0);
        $this->RegisterPropertyInteger('GridExportPower', 0);
        $this->RegisterPropertyBoolean('InvertGridPower', false);
        $this->RegisterPropertyInteger('GridImportEnergy', 0);
        $this->RegisterPropertyInteger('GridExportEnergy', 0);
        $this->RegisterPropertyInteger('GridPhaseL1', 0);
        $this->RegisterPropertyInteger('GridPhaseL2', 0);
        $this->RegisterPropertyInteger('GridPhaseL3', 0);
        $this->RegisterPropertyInteger('GridFrequency', 0);
        $this->RegisterPropertyInteger('GridVoltageL1', 0);
        $this->RegisterPropertyInteger('GridVoltageL2', 0);
        $this->RegisterPropertyInteger('GridVoltageL3', 0);
        $this->RegisterPropertyInteger('GridConnectedStatus', 0);

        // Wechselrichter-Messwerte für die originale Sunsynk-Anzeige.
        $this->RegisterPropertyInteger('InverterPower', 0);
        $this->RegisterPropertyInteger('InverterCurrentL1', 0);
        $this->RegisterPropertyInteger('InverterCurrentL2', 0);
        $this->RegisterPropertyInteger('InverterCurrentL3', 0);
        $this->RegisterPropertyInteger('HousePower', 0);
        $this->RegisterPropertyInteger('HouseEnergy', 0);
        $this->RegisterPropertyInteger('AutarkyVariable', 0);
        $this->RegisterPropertyInteger('SelfConsumptionVariable', 0);

        // auto: konfigurierte Hausverbrauchsvariable verwenden, sonst Bilanz
        // balance: PV + Batterie + Netzsaldo
        // inverter-grid: Wechselrichterleistung + Netzbezug - Einspeisung
        $this->RegisterPropertyString('HouseCalculationMode', 'auto');
        // energy = Tagesenergien, power = aktuelle Leistungen, no = ausblenden.
        $this->RegisterPropertyString('AutarkyCalculationMode', 'energy');
        // Alte Eigenschaften bleiben zur Abwärtskompatibilität registriert,
        // werden in der neuen Sunsynk-Konfiguration aber nicht mehr angezeigt.
        $this->RegisterPropertyInteger('InverterVoltage', 0);
        $this->RegisterPropertyInteger('InverterCurrent', 0);
        $this->RegisterPropertyInteger('InverterFrequency', 0);
        $this->RegisterPropertyInteger('InverterTemperature', 0);
        $this->RegisterPropertyInteger('InverterDCTemperature', 0);

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
        $this->RegisterPropertyInteger('ColorInverter', 11776947);

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

        // JSON-Austauschfeld für Hausfarben-Presets.
        $this->RegisterPropertyString('HouseColorJson', '');

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

                // Änderungen an Listen, AUX-Zuordnung, Icons oder Layout
                // benötigen einen vollständigen Neuaufbau der Web-Komponente.
                // ApplyChanges wird bei jeder übernommenen Änderung im
                // Konfigurationsformular ausgeführt.
                $this->ReloadHtml();
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
                        ['caption' => 'Compact', 'value' => 'compact'],
                        ['caption' => 'Compact Wide (16:9)', 'value' => 'compact-wide'],
                        ['caption' => 'Lite', 'value' => 'lite'],
                        ['caption' => 'Lite Wide (16:9)', 'value' => 'lite-wide'],
                        ['caption' => 'Full', 'value' => 'full'],
                        ['caption' => 'Full Wide (16:9)', 'value' => 'full-wide'],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Solaranlagen',
                    'items'   => [
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'OutsideTemperature',
                            'caption' => 'Außentemperatur (optional, Anzeige bei der Sonne)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'SolarForecastRemaining',
                            'caption' => 'Solarprognose heute gesamt (kWh, optional)',
                        ],
                        [
                            'type'        => 'List',
                            'name'        => 'Producers',
                            'caption'     => 'PV-Strings (maximal 6 dargestellt)',
                            'rowCount'    => 6,
                            'add'         => true,
                            'delete'      => true,
                            'changeOrder' => true,
                            'columns'     => [
                                [
                                    'caption' => 'Bezeichnung',
                                    'name'    => 'Name',
                                    'width'   => '125px',
                                    'add'     => '',
                                    'edit'    => [
                                        'type' => 'ValidationTextBox',
                                    ],
                                ],
                                [
                                    'caption' => 'Leistung',
                                    'name'    => 'VariableID',
                                    'width'   => '185px',
                                    'add'     => 0,
                                    'edit'    => [
                                        'type' => 'SelectVariable',
                                    ],
                                ],
                                [
                                    'caption' => 'Spannung',
                                    'name'    => 'VoltageVariableID',
                                    'width'   => '165px',
                                    'add'     => 0,
                                    'edit'    => [
                                        'type' => 'SelectVariable',
                                    ],
                                ],
                                [
                                    'caption' => 'Strom',
                                    'name'    => 'CurrentVariableID',
                                    'width'   => '155px',
                                    'add'     => 0,
                                    'edit'    => [
                                        'type' => 'SelectVariable',
                                    ],
                                ],
                                [
                                    'caption' => 'Maximalleistung',
                                    'name'    => 'MaxPower',
                                    'width'   => '115px',
                                    'add'     => 0,
                                    'edit'    => [
                                        'type'    => 'NumberSpinner',
                                        'minimum' => 0,
                                        'maximum' => 1000000,
                                        'suffix'  => ' W',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Batterien',
                    'items'   => [
                        [
                            'type'     => 'List',
                            'name'     => 'Batteries',
                            'caption'     => 'Batterien (maximal 2 dargestellt)',
                            'rowCount'    => 2,
                            'add'         => true,
                            'delete'      => true,
                            'changeOrder' => true,
                            'columns'     => [
                                [
                                    'caption' => 'Name',
                                    'name'    => 'Name',
                                    'width'   => '120px',
                                    'add'     => '',
                                    'edit'    => ['type' => 'ValidationTextBox'],
                                ],
                                [
                                    'caption' => 'Leistung',
                                    'name'    => 'VariableID',
                                    'width'   => '190px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'SOC',
                                    'name'    => 'SoCVariableID',
                                    'width'   => '150px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Strom (A)',
                                    'name'    => 'CurrentVariableID',
                                    'width'   => '150px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Spannung (V)',
                                    'name'    => 'VoltageVariableID',
                                    'width'   => '150px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Temperatur (°C)',
                                    'name'    => 'TemperatureVariableID',
                                    'width'   => '155px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Status (optional)',
                                    'name'    => 'StatusVariableID',
                                    'width'   => '190px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Max. Entladezustand Variable',
                                    'name'    => 'MaxDischargeSoCVariableID',
                                    'width'   => '180px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Kapazität (kWh)',
                                    'name'    => 'CapacityKWh',
                                    'width'   => '105px',
                                    'add'     => 0.0,
                                    'edit'    => [
                                        'type'          => 'NumberSpinner',
                                        'minimum'       => 0,
                                        'maximum'       => 10000,
                                        'digits'        => 2,
                                        'suffix'        => ' kWh',
                                    ],
                                ],
                                [
                                    'caption' => 'Entladeenergie (kWh)',
                                    'name'    => 'DischargeEnergyVariableID',
                                    'width'   => '170px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Ladeenergie (kWh)',
                                    'name'    => 'ChargeEnergyVariableID',
                                    'width'   => '170px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Fluss umkehren',
                                    'name'    => 'InvertFlow',
                                    'width'   => '85px',
                                    'add'     => false,
                                    'edit'    => ['type' => 'CheckBox'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Netz & Smart Meter',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Aktuelle Netzleistung',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'L1',
                            'caption' => 'Netzleistung gesamt (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'InvertGridPower',
                            'caption' => 'Vorzeichen der Netzleistung umkehren',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridExportPower',
                            'caption' => 'Separate Einspeiseleistung (W, optional)',
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Energiezähler',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridImportEnergy',
                            'caption' => 'Netzbezug gesamt (kWh)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridExportEnergy',
                            'caption' => 'Netzeinspeisung gesamt (kWh)',
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Phasenwerte des Smart Meters (optional)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridPhaseL1',
                            'caption' => 'Leistung Phase L1 (W)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridPhaseL2',
                            'caption' => 'Leistung Phase L2 (W)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridPhaseL3',
                            'caption' => 'Leistung Phase L3 (W)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridVoltageL1',
                            'caption' => 'Spannung Phase L1 (V)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridVoltageL2',
                            'caption' => 'Spannung Phase L2 (V)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridVoltageL3',
                            'caption' => 'Spannung Phase L3 (V)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridFrequency',
                            'caption' => 'Netzfrequenz (Hz)',
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Wechselrichter & Hausverbrauch',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Die Werte mehrerer Wechselrichter werden addiert und als Gesamtsumme angezeigt.',
                        ],
                        [
                            'type'        => 'List',
                            'name'        => 'Inverters',
                            'caption'     => 'Wechselrichter (alle Einträge werden berücksichtigt)',
                            'rowCount'    => 5,
                            'add'         => true,
                            'delete'      => true,
                            'changeOrder' => true,
                            'columns'     => [
                                [
                                    'caption' => 'Name',
                                    'name'    => 'Name',
                                    'width'   => '130px',
                                    'add'     => '',
                                    'edit'    => ['type' => 'ValidationTextBox'],
                                ],
                                [
                                    'caption' => 'Leistung (W)',
                                    'name'    => 'PowerVariableID',
                                    'width'   => '190px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Strom L1 (A)',
                                    'name'    => 'CurrentL1VariableID',
                                    'width'   => '165px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Strom L2 (A)',
                                    'name'    => 'CurrentL2VariableID',
                                    'width'   => '165px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Strom L3 (A)',
                                    'name'    => 'CurrentL3VariableID',
                                    'width'   => '165px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Tagesenergie (kWh)',
                                    'name'    => 'DailyEnergyVariableID',
                                    'width'   => '180px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Gesamtenergie (kWh)',
                                    'name'    => 'TotalEnergyVariableID',
                                    'width'   => '185px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                            ],
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Temperaturen des zentral dargestellten Wechselrichters',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'InverterTemperature',
                            'caption' => 'AC-Temperatur (°C, optional)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'InverterDCTemperature',
                            'caption' => 'DC-Temperatur (°C, optional)',
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Hausverbrauch',
                        ],
                        [
                            'type'    => 'Select',
                            'name'    => 'HouseCalculationMode',
                            'caption' => 'Berechnung des Hausverbrauchs',
                            'options' => [
                                [
                                    'caption' => 'Automatisch: Variable verwenden, sonst PV + Batterie + Netz',
                                    'value'   => 'auto',
                                ],
                                [
                                    'caption' => 'PV + Batterie + Netzbezug − Netzeinspeisung',
                                    'value'   => 'balance',
                                ],
                                [
                                    'caption' => 'Wechselrichter gesamt + Netzbezug − Netzeinspeisung',
                                    'value'   => 'inverter-grid',
                                ],
                            ],
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'HousePower',
                            'caption' => 'Hausverbrauch (W, nur bei Automatisch)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'HouseEnergy',
                            'caption' => 'Hausverbrauch heute (kWh, nur bei Automatisch)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'AutarkyVariable',
                            'caption' => 'Autarkie (%, optional – sonst interne Berechnung)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'SelfConsumptionVariable',
                            'caption' => 'Eigenverbrauch (%, optional – sonst interne Berechnung)',
                        ],
                        [
                            'type'    => 'Select',
                            'name'    => 'AutarkyCalculationMode',
                            'caption' => 'Autarkie und Eigenverbrauch',
                            'options' => [
                                [
                                    'caption' => 'Tagesenergie (kWh)',
                                    'value'   => 'energy',
                                ],
                                [
                                    'caption' => 'Aktuelle Leistung (W)',
                                    'value'   => 'power',
                                ],
                                [
                                    'caption' => 'Nicht anzeigen',
                                    'value'   => 'no',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Verbraucher',
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
                                    'width'   => '170px',
                                    'add'     => '',
                                    'edit'    => ['type' => 'ValidationTextBox'],
                                ],
                                [
                                    'caption' => 'Leistungs-Variable',
                                    'name'    => 'VariableID',
                                    'width'   => '230px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Tagesverbrauch (optional)',
                                    'name'    => 'DailyVariableID',
                                    'width'   => '210px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Anzeigeschwelle',
                                    'name'    => 'DisplayThreshold',
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => [
                                        'type' => 'NumberSpinner',
                                        'minimum' => 0,
                                        'maximum' => 100000,
                                        'suffix' => ' W',
                                    ],
                                ],
                                [
                                    'caption' => 'Icon',
                                    'name'    => 'Icon',
                                    'width'   => '145px',
                                    'add'     => 'plug',
                                    'edit'    => [
                                        'type' => 'SelectIcon',
                                    ],
                                ],
                                [
                                    'caption' => 'Als AUX',
                                    'name'    => 'IsAux',
                                    'width'   => '75px',
                                    'add'     => false,
                                    'edit'    => ['type' => 'CheckBox'],
                                ],
                                [
                                    'caption' => 'Wallbox',
                                    'name'    => 'IsWallbox',
                                    'width'   => '80px',
                                    'add'     => false,
                                    'edit'    => ['type' => 'CheckBox'],
                                ],
                                [
                                    'caption' => 'Fahrzeug-SOC',
                                    'name'    => 'SoCObjectID',
                                    'width'   => '220px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectObject'],
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
                        ['type' => 'SelectColor', 'name' => 'ColorConsumers', 'caption' => 'Verbraucher', 'allowTransparent' => false],
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
                            'type'    => 'Label',
                            'caption' => 'Das Feld dient nur zum Import. Beim Export wird das JSON in einem Dialog angezeigt; nach einem erfolgreichen Import wird das Eingabefeld automatisch geleert. Vor dem Export geänderte Farben zuerst übernehmen.',
                        ],
                        [
                            'type'        => 'ValidationTextBox',
                            'name'        => 'HouseColorJson',
                            'caption'     => 'JSON für Import einfügen',
                            'multiline'   => true,
                            'rowCount'    => 13,
                            'placeholder' => '{ \"name\": \"Mein Design\", \"version\": 1, \"colors\": { ... } }',
                        ],
                        [
                            'type'  => 'RowLayout',
                            'items' => [
                                [
                                    'type'    => 'Button',
                                    'caption' => 'Aktuelle Farben als JSON anzeigen',
                                    'onClick' => 'echo ENERGIE_ExportHouseColors($id);',
                                ],
                                [
                                    'type'    => 'Button',
                                    'caption' => 'JSON-Farben übernehmen',
                                    'onClick' => 'echo ENERGIE_ImportHouseColors($id, $HouseColorJson);',
                                ],
                            ],
                        ],
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
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                                'type'   => 'Image',
                                'onClick'=> "echo 'https://paypal.me/mbstern';",
                                'image'=> "data:image/jpeg;base64,/9j/4QAYRXhpZgAASUkqAAgAAAAAAAAAAAAAAP/sABFEdWNreQABAAQAAAA8AAD/7gAOQWRvYmUAZMAAAAAB/9sAhAAGBAQEBQQGBQUGCQYFBgkLCAYGCAsMCgoLCgoMEAwMDAwMDBAMDg8QDw4MExMUFBMTHBsbGxwfHx8fHx8fHx8fAQcHBw0MDRgQEBgaFREVGh8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx//wAARCABLAGQDAREAAhEBAxEB/8QAqwABAAICAwEBAAAAAAAAAAAAAAUGAgcDBAgJAQEBAAIDAQAAAAAAAAAAAAAAAAMEAgUGARAAAQMCAwMEDwMICwAAAAAAAgEDBAAFERIGIRMHMdEUFkFRcSKyk6PDJFSEFTZGZmEyCIGxQlKSIzODkaFigmOz00QlVRgRAAICAQIDBQYFBQAAAAAAAAABAgMREgQhMQVBUWEiE/BxgaGxBpHRQhQVwfEyUiP/2gAMAwEAAhEDEQA/AN+WWywr/CS63VDfkPmeUc5CICJKKCKCqbNlAd/qNpr1YvGHz0A6jaa9WLxh89AOo2mvVi8YfPQDqNpr1YvGHz0A6jaa9WLxh89AOo2mvVi8YfPQDqNpr1YvGHz0A6jaa9WLxh89AOo2mvVi8YfPQDqNpr1YvGHz0A6jaa9WLxh89ARnuVr3/wC4t+97o3PSui51+9jly5vvZezhQEnob4ajd1zw1oCeoBQCgFAeZtWfik1ZbtT3W3W22284MKU7GYceR4nCFk1DMSi4KbVHHYldDT0eEoJtvLRrrN7JSaSIr/1nr3/q7Z+y/wD6tS/wtXfL5GH76Xci4aC/FPFul1j2zVFtC3dKMWmrhGMiZEyXAd6B98Iqv6WZcOzVTc9HcYuUHnHYTVb1N4Zv6tIXhQCgFAV/569g85QGWhvhqN3XPDWgJ6gFAKA4LhLbhwJMxxcG4zRvGq9psVJfzVlGOWkeN4WT53SZJyZD0lxcTfMnTVe2aqS/nru0sLBz74s6XSj7SVD6rJfTR+g+6ZIAjiRKgiiY44rsSitZ44JcT6E6Nv8ADvunok2Kpd6KNPgf3wdbREISw/prkd3t5U2OMjZbHeQ3FanHkTdVi2KAUBX/AJ69g85QGWhvhqN3XPDWgJ6gFAKAp/F+6LbOGOpZaLlLoLrIL/afTcp/W5VrYw1XRXiRXvEGeElElHKAqRLsERTFVVewiJXZS5GjTXNmAWi7GSCEJ9SXYibo+aq2h9xk9zUuco/ii26T0VKalt3C6AjaMrmYjLgpKachHhyYdqrNVLzlmj6l1aMouuvjnm/yPWPBCG8zpJ19xFQZUozax7IiIhin94VrnOuTTuS7om5+2q3Hbtv9UvyRsKtMdEKAUBX/AJ69g85QGWhvhqN3XPDWgJ6gFAKA1F+KK59E4XnGQsCuE2Oxh2xFVeX/ACq2nSIZuz3JlTeSxA8waGY3l9RzDYy0Z4/auAp4VdZHmct1aeKH4tI2xpzTl11Fcfd9uESfQCdJXCyigjgiqq7eyqVjudzCmOqXI5/Z7Ke4nohz5l8snAu6HIA7zMaZjIuJtRlI3CTtZiQRHu7a1F/XYJeRNvxOg232xNyzbJKPhzNwwYMWBDZhxG0ajRwRtpseRBHYlc3ZNzk5Pi2djVXGuKjFYijnrAzFAKAr/wA9ewecoDLQ3w1G7rnhrQE9QCgFAUzidwvtnEC3QoNwmyITcJ5XwWPkXMRAod8hiXIi7Kt7TduhtpJ5IbqVNYZp7UfBCFodyO7ZnZ10dnIYPKbYkLYtqKphuhTaSr2e1XRdO6h6revTHByv3BtmowjBOXF9hduB1knx7hc50qM6wKNAw0roEGZSJSLDMicmVKq9cvjKMYpp8cnv2ztpxnOUk1wxx9vA29XOHXigFAKAUBX/AJ69g85QGWhvhqN3XPDWgNAyeKvFSdB1ZqS36lhQbTY5xsQ7e+wwrj4K4qADSqKqSoOXl5a6JbOhOEHFuUlz4mud02m0+CNl2HjvpKPpawytX3Fm3Xy5xQffiNg4eVCVUF0hBD3YuCmdM3YWtfZ06bnJVrMUyxHcR0rVzJ5njHw3eisTG7yBRJMz3czI3TyNlJyiWTMoYJ3pouK7KgexuTxp44z8CRXw7yQvOvdM2y7rYXZo+/SiuS24IiZkjbYEeYyEVEEwBfvKlY1bWc0pY8ucGN16hFvtSbNadfNfsabjaiO7xXAefVkbcTTe8JBVcSwFEXL3tdB+w27tdWh8Fzyzj/5TdxpVznHjLGnCybGd4kaSiOtxbhPCPOyCUhlEM0aNRRVAiEVRFTkwrSrpt0lmMcx+p0b6xt4NRnLEscefDwIy6a2emah0tGsEpCgXQ3XJJ7vabTRYKnfpmH7h7anq2SjXY7F5o4x737IrX9Sc7qY0vyTznh2L3+5lh1pqVrTGlLpf3W98NuYJ4WVLLnNNgBmwXDMSonJWv29XqTUe83Vk9MWzWjf4jrYPDTrZJgC3dHJbkGNZhexzutoJqSuKCKgI2aES5fs7NbB9Kl62hPy4zkr/ALtaNXaWuBxb04xpOy3vVD7Vll3ljpLFuQjkO5FxUVEQDeEmXBVXLhVaWym5yjDzKPaSq9KKcuGS02DUNk1Da2rrZZjc63vYo2+3jhiK4EioqIqKi8qKlVrKpQlpksMkjJSWUdD569g85UZkcGmSlDolSiBvZQtSFjtoqIpOIpZBxXBExKsoYys8jx8jWHCf8PVhTTrczXdl3uoCkOuE068RCLeKICELR7tccFL8tbje9TlrxVLy4KdO1WPMuJxM6R4h6Y1/q2XbNJRb/Evyf8ZOdeZaajMoK5WVA9uVBwBQRExypguFeu+qyqCc3Fx5rvGicZPCzkgLzojqx+G9+FqdBtt8W5dOhMKQkayVcRsGx3akmJMivIuxO5U1e49Td5hxjpx8P7kcq9NWHweS5aI4d6kj6KvmpLuBzteapj/vd4oi40w5gIspjlQVyd8SdwexUM93X68IrhVBkW5oslt54WbJL6lt0hwv0/CtsCVcbeJXoAE3ycMjQXeX7mZW1y9yot51SyUpKMvJ/T6kHT+iUwhGU4/9O33/AEKzE01re3WO+WIbA1MdnOOGt2J1vExPBO9QlzKX6Q4qmC1fnuaJ2Qs1uOn9OGauGz3VdVlXpqTlnzZXt7iW01o++QdR2WTIiKMS0Wnd5s4LjKczEYIiLjji6u3kqtut5XKqaT805/L2Rc2XT7YX1uS8sK/D/J5z9SF11B4q604XJa5tjbg3i43NtqVEYdBRagNkh70yJxUVVIU2Cv5Kh28qKrtSlmKj8zdWKc4YxxyQnEfgA63EusvS7DlxuF7ksNNxl3bbUCNsKQYKRJmU1aBFXlw2VNtepZaU+CivxfYYW7b/AF7Tk1fw51fbeIQXq2QblcbMlsj26CdlnNQpUbo4CCtkryLi2WVS2duvKN1XKrS3FS1NvUspns6ZKWVnGOw2bwp0m3pjR0eAkJ23OvOuypEJ+QMtxs3S5CeAQElyiOOCcta7eXepZnOfhgsUw0xwd/569g85VUlMtDfDUb7Ccx/bWgJ6gFAdO42a0XJWVuMJiYsY95H6Q0Du7P8AWDOi5V+1KzjZKPJ4PHFPmdysD0UAoBQCgFAKAUBX8U69YY7egcn8ygIeLj0iZuen/wAc83unDo2P879L9bLsoDs+k/UHkKAek/UHkKAek/UHkKAek/UHkKAek/UHkKAek/UHkKAek/UHkKAek/UHkKAek/UHkKAek/UHkKAek/UHkKAiv3fvf/db/P8A4nvT+H4nd0B//9k="
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => "Sag danke und unterstütze den Modulentwickler: paypal.me/mbstern"
                        ],
                    ],
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
            $newLayout = in_array($requestedLayout, ['compact', 'compact-wide', 'lite', 'lite-wide', 'full', 'full-wide'], true) ? $requestedLayout : 'lite';

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

    public function ExportHouseColors(): string
    {
        try {
            $colors = [];
            foreach ($this->GetHouseColorProperties() as $property) {
                $colors[$property] = $this->ReadPropertyInteger($property);
            }

            $json = json_encode(
                [
                    'name'    => 'Hausfarben',
                    'version' => 1,
                    'colors'  => $colors,
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
            );

            // Das Export-JSON nur als Dialoginhalt zurückgeben.
            // Dadurch wird das Eingabefeld nicht verändert und IP-Symcon
            // erkennt keine ungespeicherte Konfigurationsänderung.
            return $json;
        } catch (Throwable $e) {
            $this->LogMessage('ExportHouseColors: ' . $e->getMessage(), KL_ERROR);
            return 'Fehler beim JSON-Export: ' . $e->getMessage();
        }
    }

    public function ImportHouseColors(string $json): string
    {
        try {
            $json = trim($json);
            if ($json === '') {
                return 'Bitte zuerst eine JSON-Farbkonfiguration einfügen.';
            }

            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return 'Die JSON-Farbkonfiguration ist ungültig.';
            }

            // Unterstützt das dokumentierte Format mit "colors" sowie
            // einfache JSON-Objekte, die die Farbnamen direkt enthalten.
            $source = isset($decoded['colors']) && is_array($decoded['colors'])
                ? $decoded['colors']
                : $decoded;

            $imported = 0;
            foreach ($this->GetHouseColorProperties() as $property) {
                if (!array_key_exists($property, $source)) {
                    continue;
                }

                $color = $this->NormalizeImportedColor($source[$property]);
                if ($color === null) {
                    throw new InvalidArgumentException(
                        sprintf('Ungültiger Farbwert bei %s.', $property)
                    );
                }

                IPS_SetProperty($this->InstanceID, $property, $color);
                $imported++;
            }

            if ($imported === 0) {
                return 'Im JSON wurden keine bekannten Hausfarben gefunden.';
            }

            // Das JSON ist nur eine vorübergehende Eingabe und soll nicht
            // dauerhaft in der Instanzkonfiguration gespeichert bleiben.
            IPS_SetProperty($this->InstanceID, 'HouseColorJson', '');
            IPS_ApplyChanges($this->InstanceID);

            // Auch den aktuell geöffneten Formulareditor sofort leeren.
            $this->UpdateFormField('HouseColorJson', 'value', '');
            $this->ReloadForm();

            return sprintf('%d Hausfarben wurden übernommen.', $imported);
        } catch (JsonException $e) {
            return 'Ungültiges JSON: ' . $e->getMessage();
        } catch (Throwable $e) {
            $this->LogMessage('ImportHouseColors: ' . $e->getMessage(), KL_ERROR);
            return 'Fehler beim JSON-Import: ' . $e->getMessage();
        }
    }

    private function GetHouseColorProperties(): array
    {
        return [
            'HouseColorFacade',
            'HouseColorRoof',
            'HouseColorRoofSecondary',
            'HouseColorWindows',
            'HouseColorSolarPanels',
            'HouseColorInverter',
            'HouseColorCar',
            'HouseColorCarDetails',
            'HouseColorBattery',
            'HouseColorBatteryAccent',
        ];
    }

    private function NormalizeImportedColor(mixed $value): ?int
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            $color = (int) $value;
            return ($color >= 0 && $color <= 0xFFFFFF) ? $color : null;
        }

        if (is_string($value)) {
            $hex = ltrim(trim($value), '#');
            if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) === 1) {
                return hexdec($hex);
            }
        }

        return null;
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
    #technical-layout-button,
    #technical-wide-button {
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
    #technical-layout-button:hover,
    #technical-wide-button:hover {
        color: var(--w-text);
        border-color: var(--w-text2);
    }

    #display-mode-button:active,
    #technical-layout-button:active,
    #technical-wide-button:active {
        transform: translateY(1px);
    }

    #technical-wide-button.active {
        color: var(--w-text);
        border-color: var(--w-text2);
        background: color-mix(
            in srgb,
            var(--w-surface) 82%,
            var(--w-text2)
        );
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
        color: var(--ef-consumer, #2fa98f);
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
        #technical-layout-button,
        #technical-wide-button {
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
        <button id="technical-layout-button" type="button" title="Technikansicht wechseln" aria-label="Technikansicht wechseln">L</button>
        <button id="technical-wide-button" type="button" title="Wide-Ansicht umschalten" aria-label="Wide-Ansicht umschalten">⬌</button>
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
        home: '#4d9fff',
        inverter: '#b3b3b3'
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

    function formatBatteryDuration(hours) {
        const value = Number(hours);

        if (!Number.isFinite(value) || value <= 0) {
            return '';
        }

        const totalMinutes = Math.max(1, Math.round(value * 60));
        const days = Math.floor(totalMinutes / 1440);
        const remainingAfterDays = totalMinutes % 1440;
        const hrs = Math.floor(remainingAfterDays / 60);
        const mins = remainingAfterDays % 60;

        const parts = [];

        if (days > 0) parts.push(`${days} d`);
        if (hrs > 0) parts.push(`${hrs} h`);
        if (mins > 0 || parts.length === 0) parts.push(`${mins} min`);

        return parts.join(' ');
    }

    function batteryTimeEstimate(bat) {
        if (!bat || !bat.hasSoc) {
            return '';
        }

        const capacity = Number(bat.capacityKWh || 0);

        // Ohne konfigurierte Kapazität keinerlei Restzeitanzeige.
        if (!Number.isFinite(capacity) || capacity <= 0) {
            return '';
        }

        const soc = Number(bat.soc || 0);
        const powerW = Number(bat.value || 0);

        if (
            !Number.isFinite(soc) ||
            !Number.isFinite(powerW) ||
            Math.abs(powerW) < 50
        ) {
            return '';
        }

        const powerKW = Math.abs(powerW) / 1000;

        // Modulkonvention:
        // positiv = Batterie entlädt
        // negativ = Batterie lädt
        if (powerW > 0) {
            const shutdownSoc = Math.max(
                0,
                Math.min(100, Number(bat.maxDischargeSoc || 0))
            );

            const usablePercent = Math.max(0, soc - shutdownSoc);
            const remainingKWh = capacity * usablePercent / 100;

            if (remainingKWh <= 0) {
                return 'Entladegrenze erreicht';
            }

            return `Leer in ${formatBatteryDuration(remainingKWh / powerKW)}`;
        }

        const missingPercent = Math.max(0, 100 - soc);
        const missingKWh = capacity * missingPercent / 100;

        if (missingKWh <= 0) {
            return 'Voll geladen';
        }

        return `Voll in ${formatBatteryDuration(missingKWh / powerKW)}`;
    }

    function dominantHouseSourceColour(grid, pvs, batteries) {
        const solarPower = pvs.reduce(
            (sum, pv) => sum + Math.max(Number(pv.value || 0), 0),
            0
        );

        const batteryPower = batteries.reduce(
            (sum, battery) =>
                sum + Math.max(Number(battery.value || 0), 0),
            0
        );

        const gridPower = Math.max(Number(grid || 0), 0);

        const sources = [
            { power: solarPower, colour: AC.solar },
            { power: batteryPower, colour: AC.discharge },
            { power: gridPower, colour: AC.import }
        ].sort((a, b) => b.power - a.power);

        return sources[0].power > 0
            ? sources[0].colour
            : AC.room;
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
            const hasDailyEnergy = !!d.available?.inverterDailyEnergy;
            const dailyEnergyLine = hasDailyEnergy
                ? fmtKwh(d.inverterDailyEnergy || 0)
                : '';

            if (window.matchMedia('(max-width: 600px)').matches) {
                pvSub.textContent = dailyEnergyLine;
            } else {
                const stringLines = pvs.map((pv, i) => {
                    const name = pv.name || ('PV ' + (i + 1));
                    return `${name}: ${fmt(pv.value || 0)}`;
                });

                pvSub.innerHTML = [dailyEnergyLine, ...stringLines]
                    .filter(Boolean)
                    .join('<br>');
            }
        }

        // Haus
        const homeMain = document.getElementById('pfc-home-main');
        const homeEnergy = document.getElementById('pfc-home-energy');

        if (homeMain) {
            homeMain.textContent = fmt(haus);

            const dynamicHouseColour = dominantHouseSourceColour(
                grid,
                pvs,
                batteries
            );

            homeMain.style.color = dynamicHouseColour;

            const homeInfo = document.getElementById('pfc-info-home');
            if (homeInfo) {
                homeInfo.style.borderColor = dynamicHouseColour;
            }
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
                    const estimate = batteryTimeEstimate(mainBat);
                    batterySub.innerHTML =
                        `${Math.round(Number.isFinite(mainSoc) ? mainSoc : 0)} % SOC` +
                        (estimate ? `<br>${estimate}` : '');
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

                        const estimate = batteryTimeEstimate(bat);
                        const time = estimate
                            ? `<br>${estimate}`
                            : '';

                        return `${name}: ${Math.round(bat.soc || 0)} % · ${mode}${time}${energy}`;
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

        if (customElements.get('ha-icon')) {
            return;
        }

        const symconKitIcons = new Set([
            'heatpump',
            'marquee-closed',
            'marquee-half',
            'marquee-open',
            'terrace-door-closed',
            'terrace-door-open',
            'terrace-door-tilted',
            'window-closed',
            'window-open',
            'window-tilted'
        ]);

        const normalizeSymconIcon = value => {
            return String(value || '')
                .trim()
                .toLowerCase()
                .replace(/^mdi:/, '')
                .replace(/^symcon:/, '')
                .replace(/^fa-(light|solid|regular|brands|kit)\s+fa-/, '')
                .replace(/^fa-/, '')
                .replace(/_/g, '-')
                .replace(/\s+/g, '-')
                .replace(/[^a-z0-9-]/g, '') || 'plug';
        };

        customElements.define('ha-icon', class extends HTMLElement {
            static get observedAttributes() {
                return ['icon'];
            }

            constructor() {
                super();
                this._icon = '';
            }

            connectedCallback() {
                this.fixSunsynkContainer();
                this.render();
            }

            attributeChangedCallback() {
                this.render();
            }

            set icon(value) {
                this._icon = String(value || '');
                this.render();
            }

            get icon() {
                return this._icon || this.getAttribute('icon') || '';
            }

            fixSunsynkContainer() {
                /*
                 * Die originale Sunsynk-Funktion renderIcon() setzt im
                 * foreignObject einen DIV mit position:fixed. Außerhalb von
                 * Home Assistant kann dieser DIV dadurch am Bildschirmrand
                 * statt innerhalb des SVG-Platzhalters landen.
                 *
                 * Nur diese eine Eigenschaft korrigieren. x/y sowie Breite
                 * und Höhe des foreignObject bleiben vollständig bei Sunsynk.
                 */
                const container = this.parentElement;

                if (container) {
                    container.style.setProperty(
                        'position',
                        'relative',
                        'important'
                    );
                    container.style.setProperty(
                        'display',
                        'flex',
                        'important'
                    );
                    container.style.setProperty(
                        'align-items',
                        'center',
                        'important'
                    );
                    container.style.setProperty(
                        'justify-content',
                        'center',
                        'important'
                    );
                    container.style.setProperty(
                        'overflow',
                        'hidden',
                        'important'
                    );
                }
            }

            render() {
                const iconName = normalizeSymconIcon(this.icon);
                const styleClass = symconKitIcons.has(iconName)
                    ? 'fa-kit'
                    : 'fa-light';

                this.fixSunsynkContainer();

                /*
                 * Home Assistants ha-icon besitzt selbst eine definierte
                 * Fläche. Diese minimale Größe benötigt auch der Symcon-
                 * Ersatz, damit das Font-Icon überhaupt sichtbar wird.
                 */
                this.style.setProperty(
                    'display',
                    'inline-flex',
                    'important'
                );
                this.style.setProperty(
                    'align-items',
                    'center',
                    'important'
                );
                this.style.setProperty(
                    'justify-content',
                    'center',
                    'important'
                );
                this.style.setProperty(
                    'width',
                    'var(--mdc-icon-size, 24px)',
                    'important'
                );
                this.style.setProperty(
                    'height',
                    'var(--mdc-icon-size, 24px)',
                    'important'
                );
                this.style.setProperty(
                    'line-height',
                    '1',
                    'important'
                );
                // Alle Verbraucher-Icons verwenden unabhängig vom
                // gewählten Symbol dieselbe konfigurierte Verbraucherfarbe.
                this.style.setProperty(
                    'color',
                    AC.room,
                    'important'
                );

                let icon = this.querySelector(':scope > i');

                if (!icon) {
                    this.replaceChildren();
                    icon = document.createElement('i');
                    this.appendChild(icon);
                }

                icon.className = `${styleClass} fa-${iconName}`;
                icon.setAttribute('aria-hidden', 'true');
                icon.style.setProperty(
                    'display',
                    'block',
                    'important'
                );
                icon.style.setProperty(
                    'font-size',
                    'var(--mdc-icon-size, 24px)',
                    'important'
                );
                icon.style.setProperty(
                    'line-height',
                    '1',
                    'important'
                );
                icon.style.setProperty(
                    'color',
                    'currentColor',
                    'important'
                );
                icon.style.setProperty(
                    'pointer-events',
                    'none',
                    'important'
                );

                /*
                 * /icons.js stellt Font Awesome als JavaScript-Renderer bereit.
                 * Die CSS-Klassen allein greifen innerhalb des Shadow DOM der
                 * Sunsynk-Karte nicht zuverlässig. Deshalb wird das <i> direkt
                 * in ein Inline-SVG umgewandelt. Dieses SVG benötigt anschließend
                 * keine Font-Awesome-CSS-Regeln mehr.
                 */
                this.renderFontAwesomeSvg();
            }

            applyConsumerColourToSvg() {
                const colour = AC.room;

                this.style.setProperty(
                    'color',
                    colour,
                    'important'
                );

                this.querySelectorAll('svg').forEach(svg => {
                    svg.setAttribute('color', colour);
                    svg.setAttribute('fill', colour);
                    svg.style.setProperty(
                        'color',
                        colour,
                        'important'
                    );
                    svg.style.setProperty(
                        'fill',
                        colour,
                        'important'
                    );
                });

                this.querySelectorAll(
                    'svg path, svg g, svg polygon, svg circle, ' +
                    'svg rect, svg ellipse, svg polyline'
                ).forEach(node => {
                    node.setAttribute('fill', colour);
                    node.setAttribute('color', colour);
                    node.style.setProperty(
                        'fill',
                        colour,
                        'important'
                    );
                    node.style.setProperty(
                        'color',
                        colour,
                        'important'
                    );

                    if (
                        node.hasAttribute('stroke') &&
                        node.getAttribute('stroke') !== 'none'
                    ) {
                        node.setAttribute('stroke', colour);
                        node.style.setProperty(
                            'stroke',
                            colour,
                            'important'
                        );
                    }
                });
            }

            moveIconIntoNativeSvg() {
                const sourceSvg = this.querySelector('svg');
                const foreignObject = this.closest('foreignObject');

                if (!sourceSvg || !foreignObject) {
                    return;
                }

                const svgParent = foreignObject.parentNode;
                if (!svgParent) {
                    return;
                }

                const x = Number(foreignObject.getAttribute('x') || 0);
                const y = Number(foreignObject.getAttribute('y') || 0);
                const width = Number(
                    foreignObject.getAttribute('width') || 24
                );
                const height = Number(
                    foreignObject.getAttribute('height') || 24
                );

                const viewBox = String(
                    sourceSvg.getAttribute('viewBox') || '0 0 512 512'
                )
                    .trim()
                    .split(/\s+/)
                    .map(Number);

                const vbX = Number.isFinite(viewBox[0])
                    ? viewBox[0]
                    : 0;
                const vbY = Number.isFinite(viewBox[1])
                    ? viewBox[1]
                    : 0;
                const vbWidth =
                    Number.isFinite(viewBox[2]) && viewBox[2] > 0
                        ? viewBox[2]
                        : 512;
                const vbHeight =
                    Number.isFinite(viewBox[3]) && viewBox[3] > 0
                        ? viewBox[3]
                        : 512;

                // Das Icon etwas kleiner als den von Sunsynk
                // vorgesehenen Platzhalter darstellen und mittig halten.
                const iconScaleFactor = 0.82;
                const scale = Math.min(
                    width / vbWidth,
                    height / vbHeight
                ) * iconScaleFactor;

                // Kleiner vertikaler Abstand zur Leistungsbox:
                // das Icon 3 px nach oben verschieben.
                const iconOffsetY = -3;

                /*
                 * Die horizontale Position vollständig von Sunsynk übernehmen.
                 * Der von Sunsynk erzeugte foreignObject-Platzhalter sitzt
                 * bereits an der für die jeweilige Ansicht vorgesehenen Stelle.
                 * Wir verändern daher nur Größe und vertikalen Abstand.
                 */
                const translateX =
                    x + ((width - (vbWidth * scale)) / 2) -
                    (vbX * scale);

                const translateY =
                    y + ((height - (vbHeight * scale)) / 2) -
                    (vbY * scale) +
                    iconOffsetY;

                if (!foreignObject.dataset.symconIconId) {
                    foreignObject.dataset.symconIconId =
                        `symcon-native-icon-${Math.random()
                            .toString(36)
                            .slice(2)}`;
                }

                const iconId =
                    foreignObject.dataset.symconIconId;

                svgParent
                    .querySelectorAll?.(
                        `[data-symcon-native-icon="${iconId}"]`
                    )
                    .forEach(node => node.remove());

                const group = document.createElementNS(
                    'http://www.w3.org/2000/svg',
                    'g'
                );

                group.setAttribute(
                    'data-symcon-native-icon',
                    iconId
                );
                group.setAttribute(
                    'transform',
                    `translate(${translateX} ${translateY}) ` +
                    `scale(${scale})`
                );
                group.setAttribute('pointer-events', 'none');
                group.setAttribute('fill', AC.room);
                group.setAttribute('color', AC.room);

                Array.from(sourceSvg.childNodes).forEach(child => {
                    const cloned = child.cloneNode(true);

                    if (cloned.nodeType === Node.ELEMENT_NODE) {
                        cloned.setAttribute?.('fill', AC.room);
                        cloned.setAttribute?.('color', AC.room);
                    }

                    group.appendChild(cloned);
                });

                group.querySelectorAll?.(
                    'path, g, polygon, circle, rect, ellipse, polyline'
                ).forEach(node => {
                    node.setAttribute('fill', AC.room);
                    node.setAttribute('color', AC.room);
                    node.style?.setProperty(
                        'fill',
                        AC.room,
                        'important'
                    );

                    if (
                        node.hasAttribute('stroke') &&
                        node.getAttribute('stroke') !== 'none'
                    ) {
                        node.setAttribute('stroke', AC.room);
                    }
                });

                svgParent.insertBefore(
                    group,
                    foreignObject.nextSibling
                );

                /*
                 * Auf Mobilgeräten ist HTML in SVG-foreignObject unzuverlässig.
                 * Nach der Übernahme als echtes SVG wird der HTML-Platzhalter
                 * deshalb ausgeblendet. Position und Größe stammen weiterhin
                 * vollständig von Sunsynk.
                 */
                foreignObject.style.setProperty(
                    'display',
                    'none',
                    'important'
                );
            }

            renderFontAwesomeSvg(attempt = 0) {
                const fontAwesome = window.FontAwesome;

                if (
                    fontAwesome?.dom &&
                    typeof fontAwesome.dom.i2svg === 'function'
                ) {
                    requestAnimationFrame(() => {
                        try {
                            fontAwesome.dom.i2svg({
                                node: this
                            });

                            // i2svg arbeitet teilweise asynchron. Die Farbe
                            // deshalb unmittelbar und nochmals kurz danach
                            // auf das tatsächlich erzeugte SVG anwenden.
                            this.applyConsumerColourToSvg();

                            [0, 30, 120].forEach(delay => {
                                setTimeout(() => {
                                    this.applyConsumerColourToSvg();
                                    this.moveIconIntoNativeSvg();
                                }, delay);
                            });
                        } catch (_) {
                            // Ein weiterer Renderdurchlauf versucht es erneut.
                        }
                    });

                    return;
                }

                // /icons.js kann beim ersten Sunsynk-Render noch nicht fertig
                // initialisiert sein. Kurz warten, ohne die Karte zu blockieren.
                if (attempt < 30 && this.isConnected) {
                    setTimeout(
                        () => this.renderFontAwesomeSvg(attempt + 1),
                        100
                    );
                }
            }
        });
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

    function entityAvailable(d, key) {
        return !!d?.available?.[key];
    }

    function normalizeConsumerIcon(icon) {
        const raw = String(icon || '')
            .trim()
            .toLowerCase()
            .replace(/^mdi:/, '')
            .replace(/^symcon:/, '')
            .replace(/^fa-(light|solid|regular|brands|kit)\s+fa-/, '')
            .replace(/^fa-/, '')
            .replace(/_/g, '-')
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '');

        // Sunsynk erzeugt daraus den vorhandenen ha-icon-Platzhalter.
        // Unser kleiner Wrapper interpretiert den Namen anschließend als
        // IP-Symcon-/Font-Awesome-Icon.
        return `mdi:${raw || 'plug'}`;
    }

    function createSunsynkConfig(d, pvs, batteries, wallbox, groups) {
        const requestedLayout = ['compact', 'compact-wide', 'lite', 'lite-wide', 'full', 'full-wide'].includes(currentTechnicalLayout)
            ? currentTechnicalLayout
            : 'lite';
        const wide = requestedLayout.endsWith('-wide');
        const style = requestedLayout.replace('-wide', '');
        const full = style === 'full';
        const showEnergyDetails = style !== 'compact';

        const activePvs = pvs.filter(pv => pv.hasPower);
        const activeBatteries = batteries.filter(b => b.hasPower || b.hasSoc);
        const hasGrid = entityAvailable(d, 'gridPower');
        const hasWallbox = !!d.hasWallbox;

        // Die Wallbox befindet sich bereits in der normalen Verbraucherliste.
        // Verbraucher sind reine Lasten; negative Werte werden in der
        // Sunsynk-Ansicht deshalb auf 0 W begrenzt.
        const configuredConsumers = groups
            .filter(group => group.hasPower)
            .map(group => ({
                ...group,
                value: Math.max(Number(group.value || 0), 0),
                displayThreshold: Math.max(
                    Number(group.displayThreshold || 0),
                    0
                )
            }));

        // AUX bleibt von der Mindestleistung unberührt und verhält sich
        // damit exakt wie vor Einführung der Anzeigeschwelle.
        const auxGroups = configuredConsumers
            .filter(group => group.isAux === true)
            .slice(0, 2);

        // Die Mindestleistung gilt ausschließlich für normale Verbraucher.
        const normalConsumers = configuredConsumers
            .filter(group => !auxGroups.includes(group))
            .filter(group =>
                group.displayThreshold <= 0 ||
                group.value >= group.displayThreshold
            );

        // Aktive normale Verbraucher zuerst, absteigend nach Leistung.
        const activeConsumers = normalConsumers
            .filter(group => Number(group.value || 0) > 0)
            .sort((a, b) =>
                Number(b.value || 0) -
                Number(a.value || 0)
            );

        // Freie Plätze werden mit inaktiven normalen Verbrauchern aufgefüllt.
        const inactiveConsumers = normalConsumers.filter(group =>
            Number(group.value || 0) <= 0
        );

        const maxConsumers =
            full && auxGroups.length > 0
                ? 2
                : (full ? 6 : 3);

        const activeGroups = [
            ...activeConsumers,
            ...inactiveConsumers
        ].slice(0, maxConsumers);

        // Nur diese Texte dürfen später geometrisch zentriert werden.
        // Dadurch bleiben PV-Stringwerte, Spannungen, Ströme und sonstige
        // Beschriftungen vollständig unangetastet.
        window.__symconVisibleConsumerNames = activeGroups
            .map(group => String(group?.name || '').trim())
            .filter(Boolean);

        const threePhase = entityAvailable(d, 'gridPhaseL2') || entityAvailable(d, 'gridPhaseL3')
            || entityAvailable(d, 'inverterCurrentL2') || entityAvailable(d, 'inverterCurrentL3')
            || entityAvailable(d, 'gridVoltageL2') || entityAvailable(d, 'gridVoltageL3');

        const entities = {};
        const addEntity = (key, entity, available = true) => { if (available) entities[key] = entity; };

        // Originales Sunsynk-Feld für die gesamte AC-Wechselrichterleistung.
        // Dieser Sensor stammt ausschließlich aus der in Symcon konfigurierten
        // Eigenschaft „Wechselrichterleistung gesamt“.
        addEntity(
            'inverter_power_175',
            'sensor.symcon_inverter',
            entityAvailable(d, 'inverterPower') || !!d.inverterPowerAvailable
        );
        // Die Sunsynk-Karte verwendet für die sichtbare
        // Wechselrichter-/Kühlkörpertemperatur radiator_temp_91.
        addEntity(
            'radiator_temp_91',
            'sensor.symcon_inverter_temperature',
            entityAvailable(d, 'inverterTemperature')
        );
        // Zweites Temperaturfeld der Originalkarte zeigt die zentrale DC-Temperatur.
        addEntity(
            'dc_transformer_temp_90',
            'sensor.symcon_inverter2_temperature',
            entityAvailable(d, 'inverter2Temperature')
        );
        addEntity('inverter_current_164', 'sensor.symcon_inverter_current_l1', entityAvailable(d, 'inverterCurrentL1'));
        addEntity('inverter_current_L2', 'sensor.symcon_inverter_current_l2', entityAvailable(d, 'inverterCurrentL2'));
        addEntity('inverter_current_L3', 'sensor.symcon_inverter_current_l3', entityAvailable(d, 'inverterCurrentL3'));
        // grid_power_169 ist die AC-/Smartmeter-Leistung zwischen Netz und
        // Wechselrichter. Hier darf nicht die Wechselrichter-Gesamtleistung
        // verwendet werden, sonst zeigt die Smartmeter-Box einen viel zu hohen
        // Wert und die Punkte laufen nach der WR-Leistung statt nach dem
        // tatsächlichen Netzbezug bzw. der Rücklieferung.
        addEntity(
            'grid_power_169',
            'sensor.symcon_grid_power',
            hasGrid
        );
        addEntity('inverter_voltage_154', 'sensor.symcon_grid_voltage_l1', entityAvailable(d, 'gridVoltageL1'));
        addEntity('inverter_voltage_L2', 'sensor.symcon_grid_voltage_l2', entityAvailable(d, 'gridVoltageL2'));
        addEntity('inverter_voltage_L3', 'sensor.symcon_grid_voltage_l3', entityAvailable(d, 'gridVoltageL3'));
        addEntity('grid_voltage', 'sensor.symcon_grid_voltage_l1', entityAvailable(d, 'gridVoltageL1'));
        addEntity('essential_power', 'sensor.symcon_home', true);

        addEntity('grid_ct_power_172', 'sensor.symcon_grid', hasGrid);
        addEntity('grid_ct_power_total', 'sensor.symcon_grid_total', hasGrid && threePhase);
        addEntity('grid_ct_power_L2', 'sensor.symcon_grid_l2', entityAvailable(d, 'gridPhaseL2'));
        addEntity('grid_ct_power_L3', 'sensor.symcon_grid_l3', entityAvailable(d, 'gridPhaseL3'));
        addEntity('grid_connected_status_194', 'sensor.symcon_grid_status', entityAvailable(d, 'gridStatus'));
        addEntity('load_frequency_192', 'sensor.symcon_grid_frequency', entityAvailable(d, 'gridFrequency'));

        activePvs.forEach((pv, i) => {
            const stringNo = i + 1;

            addEntity(
                i < 4
                    ? `pv${stringNo}_power_${186 + i}`
                    : `pv${stringNo}_power`,
                `sensor.symcon_pv${stringNo}`
            );

            addEntity(
                `pv${stringNo}_voltage_${109 + (i * 2)}`,
                `sensor.symcon_pv${stringNo}_voltage`,
                pv.hasVoltage
            );

            addEntity(
                `pv${stringNo}_current_${110 + (i * 2)}`,
                `sensor.symcon_pv${stringNo}_current`,
                pv.hasCurrent
            );
        });

        if (entityAvailable(d, 'inverterDailyEnergy')) {
            addEntity('day_pv_energy_108', 'sensor.symcon_pv_energy');
        }

        addEntity(
            'remaining_solar',
            'sensor.symcon_solar_forecast_remaining',
            entityAvailable(d, 'solarForecastRemaining')
        );

        addEntity(
            'environment_temp',
            'sensor.symcon_outside_temperature',
            entityAvailable(d, 'outsideTemperature')
        );

        if (activeBatteries[0]) {
            addEntity('battery_soc_184', 'sensor.symcon_battery_soc', activeBatteries[0].hasSoc);
            addEntity('battery_power_190', 'sensor.symcon_battery_power', activeBatteries[0].hasPower);
            addEntity('battery_current_191', 'sensor.symcon_battery_current');
            addEntity('battery_voltage_183', 'sensor.symcon_battery_voltage', activeBatteries[0].hasVoltage);
            addEntity('battery_temp_182', 'sensor.symcon_battery_temperature', activeBatteries[0].hasTemperature);
            addEntity('battery_status', 'sensor.symcon_battery_status', activeBatteries[0].hasStatus);
            addEntity('day_battery_charge_70', 'sensor.symcon_battery_charge_energy', activeBatteries[0].hasChargeEnergy);
            addEntity('day_battery_discharge_71', 'sensor.symcon_battery_discharge_energy', activeBatteries[0].hasDischargeEnergy);
        }
        if (activeBatteries[1]) {
            addEntity('battery2_soc_184', 'sensor.symcon_battery2_soc', activeBatteries[1].hasSoc);
            addEntity('battery2_power_190', 'sensor.symcon_battery2_power', activeBatteries[1].hasPower);
            addEntity('battery2_current_191', 'sensor.symcon_battery2_current');
            addEntity('battery2_voltage_183', 'sensor.symcon_battery2_voltage', activeBatteries[1].hasVoltage);
            addEntity('battery2_temp_182', 'sensor.symcon_battery2_temperature', activeBatteries[1].hasTemperature);
            addEntity('battery2_status', 'sensor.symcon_battery2_status', activeBatteries[1].hasStatus);
            addEntity('day_battery2_charge_70', 'sensor.symcon_battery2_charge_energy', activeBatteries[1].hasChargeEnergy);
            addEntity('day_battery2_discharge_71', 'sensor.symcon_battery2_discharge_energy', activeBatteries[1].hasDischargeEnergy);
        }

        activeGroups.forEach((group, i) => {
            addEntity(`essential_load${i + 1}`, `sensor.symcon_branch${i + 1}`);
            addEntity(`essential_load${i + 1}_extra`, `sensor.symcon_branch${i + 1}_daily`, group.hasDaily);
        });

        if (auxGroups.length > 0) {
            // Der gemeinsame AUX-Zweig wird immer verwendet.
            // Bei genau einem AUX-Verbraucher ist dies der große Haupt-AUX.
            // Erst bei zwei AUX-Verbrauchern werden zusätzlich Aux1 und Aux2
            // als die beiden kleinen Unterverbraucher eingeblendet.
            addEntity('aux_power_166', 'sensor.symcon_aux_total');
            addEntity(
                'day_aux_energy',
                'sensor.symcon_aux_energy',
                auxGroups.some(group => group.hasDaily)
            );

            if (auxGroups.length >= 2) {
                addEntity('aux_load1', 'sensor.symcon_aux1', true);
                addEntity(
                    'aux_load1_extra',
                    'sensor.symcon_aux1_extra',
                    !!auxGroups[0]?.hasSoc
                );
                addEntity('aux_load2', 'sensor.symcon_aux2', true);
                addEntity(
                    'aux_load2_extra',
                    'sensor.symcon_aux2_extra',
                    !!auxGroups[1]?.hasSoc
                );
            }
        }
        addEntity('day_grid_import_76', 'sensor.symcon_grid_import_energy', d.gridImportEnergyValueAvailable);
        addEntity('day_grid_export_77', 'sensor.symcon_grid_export_energy', d.gridExportEnergyValueAvailable);
        addEntity('day_load_energy_84', 'sensor.symcon_load_energy', d.houseEnergyAvailable);

        // Die Originalkarte rendert zusätzliche Verbraucher nicht zuverlässig
        // über <ha-icon>. Deshalb merken wir die tatsächlich angezeigten Icons
        // und setzen sie nach dem Rendern direkt in die SVG-Verbraucherboxen.
        // Einheitliche Farbe für Haus, Hausverbrauch und Leitung zum Haus.
        // Dadurch kann die Verbraucherfarbe nicht mehr auf den Hauszweig
        // durchschlagen.
        const houseSourceColour = dominantHouseSourceColour(
            Number(d.grid || 0),
            activePvs,
            activeBatteries
        );

        const cfg = {
            cardstyle: style,
            wide,
            large_font: true,
            show_solar: activePvs.length > 0,
            show_battery: activeBatteries.length > 0,
            show_grid: hasGrid,
            center_no_grid: !hasGrid,
            decimal_places: 0,
            decimal_places_energy: 2,
            dynamic_line_width: true,
            max_line_width: 5,
            min_line_width: 2,
            inverter: {
                modern: true,
                model: 'goodwe',
                colour: d.houseColors?.inverter || '#0d151c',
                autarky: ['power', 'energy', 'no'].includes(d.autarkyCalculationMode)
                    ? d.autarkyCalculationMode
                    : 'energy',
                auto_scale: false,
                three_phase: threePhase,
                label_autarky: 'Autarkie',
                label_ratio: 'Eigenverbrauch'
            },
            solar: {
                colour: AC.solar,
                show_daily: showEnergyDetails && entityAvailable(d, 'inverterDailyEnergy'),
                mppts: Math.max(1, Math.min(6, activePvs.length || 1)),
                animation_speed: Math.max(1, Math.round(9 / flowSpeedFactor)),
                // Die Sunsynk-Karte besitzt nur einen gemeinsamen
                // solar.max_power-Wert. Deshalb werden die je PV-Anlage
                // konfigurierten Maximalleistungen addiert.
                max_power: Math.max(1, Number(d.solarMaxPower || 1)),
                auto_scale: false,
                display_mode: entityAvailable(d, 'solarForecastRemaining') ? 2 : 1,

                // Prozent-/Effizienzanzeige pro einzelner PV-Anlage.
                // Die Originalkarte berechnet damit:
                // aktuelle Leistung / konfigurierte Maximalleistung × 100.
                pv1_max_power: Math.max(1, Number(activePvs[0]?.maxPower || 1)),
                pv2_max_power: Math.max(1, Number(activePvs[1]?.maxPower || 1)),
                pv3_max_power: Math.max(1, Number(activePvs[2]?.maxPower || 1)),
                pv4_max_power: Math.max(1, Number(activePvs[3]?.maxPower || 1)),
                pv5_max_power: Math.max(1, Number(activePvs[4]?.maxPower || 1)),
                pv6_max_power: Math.max(1, Number(activePvs[5]?.maxPower || 1)),

                pv1_name: activePvs[0]?.name || 'PV 1', pv2_name: activePvs[1]?.name || 'PV 2',
                pv3_name: activePvs[2]?.name || 'PV 3', pv4_name: activePvs[3]?.name || 'PV 4',
                pv5_name: activePvs[4]?.name || 'PV 5', pv6_name: activePvs[5]?.name || 'PV 6'
            },
            battery: {
                count: Math.min(2, Math.max(1, activeBatteries.length)),

                // Die Originalkarte erwartet die Batteriekapazität in Wh.
                // Bei 0 wird die Restzeitanzeige von der Karte ausgeblendet.
                energy: Math.max(
                    0,
                    Number(activeBatteries[0]?.capacityKWh || 0) * 1000
                ),

                shutdown_soc: Number(activeBatteries[0]?.maxDischargeSoc || 0) === 0 ? '0' : Math.max(0, Math.min(100, Math.round(Number(activeBatteries[0]?.maxDischargeSoc || 0)))),
                soc_end_of_charge: 100,
                hide_soc: false,
                // Die Originalkarte verwendet battery.colour für den
                // äußeren Batterierahmen und den Flusspunkt. Deshalb wird
                // nur diese Farbe richtungsabhängig gesetzt. charge_colour
                // und die dynamische SOC-Füllung bleiben unverändert.
                colour: Number(activeBatteries[0]?.value || 0) > 0
                    ? AC.discharge
                    : AC.charge,
                charge_colour: AC.charge,
                show_daily: showEnergyDetails && !!activeBatteries[0] && (activeBatteries[0].hasChargeEnergy || activeBatteries[0].hasDischargeEnergy),
                animation_speed: Math.max(1, Math.round(6 / flowSpeedFactor)),
                max_power: 10000,
                auto_scale: false,
                dynamic_colour: false,
                linear_gradient: true,
                animate: true,
                show_absolute: true,
                // Der PHP-Payload enthält bereits die normalisierte Leistung:
                // negativ = Laden, positiv = Entladen. Deshalb darf die
                // Sunsynk-Karte das Vorzeichen nicht nochmals umkehren.
                invert_power: false,
                // Flussrichtung gegenüber der bisherigen Sunsynk-Darstellung
                // umdrehen. Farbe und Animation bleiben dabei zusammengehörig.
                invert_flow: false
            },
            battery2: {
                // Auch Batterie 2: Kapazität in Wh. 0 blendet die Zeit aus.
                energy: Math.max(
                    0,
                    Number(activeBatteries[1]?.capacityKWh || 0) * 1000
                ),

                shutdown_soc: Number(activeBatteries[1]?.maxDischargeSoc || 0) === 0 ? '0' : Math.max(0, Math.min(100, Math.round(Number(activeBatteries[1]?.maxDischargeSoc || 0)))),
                soc_end_of_charge: 100,
                hide_soc: false,
                colour: Number(activeBatteries[1]?.value || 0) > 0
                    ? AC.discharge
                    : AC.charge,
                charge_colour: AC.charge,
                show_daily: showEnergyDetails && !!activeBatteries[1] && (activeBatteries[1].hasChargeEnergy || activeBatteries[1].hasDischargeEnergy),
                show_absolute: true,
                auto_scale: false,
                dynamic_colour: false,
                linear_gradient: true,
                animate: true,
                // Auch Batterie 2 ist im Payload bereits normalisiert.
                invert_power: false,
                // Flussrichtung gegenüber der bisherigen Sunsynk-Darstellung
                // umdrehen. Farbe und Animation bleiben dabei zusammengehörig.
                invert_flow: false
            },
            load: {
                // Hauszweig, Haussymbol, Bezeichnung und Leistungsbox verwenden
                // dieselbe aktuell dominante Quellenfarbe.
                colour: houseSourceColour,
                off_colour: '#9e9e9e',

                // Die Farbe wird oben eindeutig berechnet. Damit verhindert
                // man, dass die Originalkarte zeitweise load.colour der
                // zusätzlichen Verbraucher auf den Hauszweig überträgt.
                dynamic_colour: false,
                dynamic_icon: false,
                show_daily: showEnergyDetails && d.houseEnergyAvailable,
                // Maximal zwei markierte Verbraucher werden rechts im
                // originalen AUX-Bereich der Sunsynk-Card dargestellt.
                show_aux: auxGroups.length > 0,
                show_daily_aux:
                    showEnergyDetails &&
                    auxGroups.some(group => group.hasDaily),
                animation_speed: Math.max(1, Math.round(4 / flowSpeedFactor)),
                max_power: 12000,
                auto_scale: false,
                additional_loads: activeGroups.length,

                // Genau ein markierter Verbraucher wird als großer Haupt-AUX
                // dargestellt. Bei zwei Einträgen zeigt die Originalkarte
                // die beiden kleinen Felder Aux1 und Aux2.
                aux_loads: auxGroups.length >= 2 ? 2 : 0,
                aux_name:
                    auxGroups.length === 1
                        ? (auxGroups[0]?.name || 'AUX')
                        : 'AUX',
                aux_daily_name:
                    auxGroups.length === 1
                        ? (auxGroups[0]?.name || 'AUX')
                        : 'AUX',
                aux_type:
                    auxGroups.length === 1
                        ? normalizeConsumerIcon(auxGroups[0]?.icon)
                        : 'default',
                aux_load1_name:
                    auxGroups.length >= 2
                        ? (auxGroups[0]?.name || 'Aux1')
                        : '',
                aux_load2_name:
                    auxGroups.length >= 2
                        ? (auxGroups[1]?.name || 'Aux2')
                        : '',
                // Haupt-AUX, Unterverbraucher, Linie, Icon, Werte und Text
                // verwenden dieselbe konfigurierte Verbraucherfarbe.
                aux_colour: AC.room,
                aux_off_colour: AC.room,
                aux_dynamic_colour: false,
                essential_name: 'Haus',
                load1_name: activeGroups[0]?.name || '', load2_name: activeGroups[1]?.name || '',
                load3_name: activeGroups[2]?.name || '', load4_name: activeGroups[3]?.name || '',
                load5_name: activeGroups[4]?.name || '', load6_name: activeGroups[5]?.name || '',
                load1_icon: normalizeConsumerIcon(activeGroups[0]?.icon),
                load2_icon: normalizeConsumerIcon(activeGroups[1]?.icon),
                load3_icon: normalizeConsumerIcon(activeGroups[2]?.icon),
                load4_icon: normalizeConsumerIcon(activeGroups[3]?.icon),
                load5_icon: normalizeConsumerIcon(activeGroups[4]?.icon),
                load6_icon: normalizeConsumerIcon(activeGroups[5]?.icon)
            },
            grid: {
                colour: AC.import,
                export_colour: AC.export,
                grid_name: 'Netz',
                show_daily_buy: showEnergyDetails && d.gridImportEnergyValueAvailable,
                show_daily_sell: showEnergyDetails && d.gridExportEnergyValueAvailable,
                show_nonessential: false,
                additional_loads: 0,
                animation_speed: Math.max(1, Math.round(8 / flowSpeedFactor)),
                max_power: 12000,
                auto_scale: false,
                show_absolute: true,
                invert_grid: false,
                invert_flow: false
            },
            entities
        };
        return cfg;
    }

    function createSunsynkHass(d, grid, haus, pvs, batteries, wallbox, groups) {
        const activePvs = pvs.filter(pv => pv.hasPower);
        const activeBatteries = batteries.filter(b => b.hasPower || b.hasSoc);
        const hasWallbox = !!d.hasWallbox;

        // Auch im virtuellen Home-Assistant-Datenmodell negative
        // Verbraucherwerte konsequent auf 0 W begrenzen.
        const configuredConsumers = groups
            .filter(group => group.hasPower)
            .map(group => ({
                ...group,
                value: Math.max(Number(group.value || 0), 0),
                displayThreshold: Math.max(
                    Number(group.displayThreshold || 0),
                    0
                )
            }));

        // AUX bleibt von der Mindestleistung unberührt und verhält sich
        // damit exakt wie vor Einführung der Anzeigeschwelle.
        const auxGroups = configuredConsumers
            .filter(group => group.isAux === true)
            .slice(0, 2);

        // Die Mindestleistung gilt ausschließlich für normale Verbraucher.
        const normalConsumers = configuredConsumers
            .filter(group => !auxGroups.includes(group))
            .filter(group =>
                group.displayThreshold <= 0 ||
                group.value >= group.displayThreshold
            );

        const activeConsumers = normalConsumers
            .filter(group => Number(group.value || 0) > 0)
            .sort((a, b) =>
                Number(b.value || 0) -
                Number(a.value || 0)
            );

        const inactiveConsumers = normalConsumers.filter(group =>
            Number(group.value || 0) <= 0
        );

        // Exakt dieselbe Reihenfolge wie in createSunsynkConfig.
        const fullLayout =
            currentTechnicalLayout.startsWith('full');

        const maxConsumers =
            fullLayout && auxGroups.length > 0
                ? 2
                : (fullLayout ? 6 : 3);

        const activeGroups = [
            ...activeConsumers,
            ...inactiveConsumers
        ].slice(0, maxConsumers);
        const bat1 = activeBatteries[0] || {};
        const bat2 = activeBatteries[1] || {};
        const pvEnergyTotal = entityAvailable(d, 'inverterDailyEnergy')
            ? Number(d.inverterDailyEnergy || 0)
            : 0;

        // Die konfigurierte Prognosevariable liefert die erwartete
        // Tagesproduktion insgesamt. Sunsynk erwartet bei remaining_solar
        // jedoch nur die noch verbleibende Energie.
        const forecastTotal = Number(d.solarForecastRemaining || 0);
        const solarForecastRemaining = Math.max(
            (Number.isFinite(forecastTotal) ? forecastTotal : 0) -
            pvEnergyTotal,
            0
        );

        const states = {
            'sensor.symcon_grid': ssState(d.gridPhaseL1Available ? d.gridPhaseL1 : grid, 'W'),
            'sensor.symcon_grid_power': ssState(grid, 'W'),
            'sensor.symcon_grid_voltage_l1': ssState(d.gridVoltageL1 || 0, 'V'),
            'sensor.symcon_grid_voltage_l2': ssState(d.gridVoltageL2 || 0, 'V'),
            'sensor.symcon_grid_voltage_l3': ssState(d.gridVoltageL3 || 0, 'V'),
            'sensor.symcon_grid_total': ssState(grid, 'W'),
            'sensor.symcon_grid_l2': ssState(d.gridPhaseL2 || 0, 'W'),
            'sensor.symcon_grid_l3': ssState(d.gridPhaseL3 || 0, 'W'),
            'sensor.symcon_grid_status': { state: String(d.gridConnectedStatus ?? 'on-grid'), attributes: {} },
            'sensor.symcon_grid_frequency': ssState(d.gridFrequency || 0, 'Hz'),
            'sensor.symcon_home': ssState(haus || 0, 'W'),
            'sensor.symcon_inverter': ssState(d.inverterPower || 0, 'W'),
            'sensor.symcon_inverter_temperature': ssState(
                d.inverterTemperature || 0,
                '°C'
            ),
            'sensor.symcon_inverter2_temperature': ssState(
                d.inverter2Temperature || 0,
                '°C'
            ),
            'sensor.symcon_inverter_current_l1': ssState(d.inverterCurrentL1 || 0, 'A'),
            'sensor.symcon_inverter_current_l2': ssState(d.inverterCurrentL2 || 0, 'A'),
            'sensor.symcon_inverter_current_l3': ssState(d.inverterCurrentL3 || 0, 'A'),
            'sensor.symcon_wallbox': ssState(wallbox?.value || 0, 'W'),
            'sensor.symcon_wallbox_energy': ssState(wallbox?.energyValue || 0, 'kWh'),
            'sensor.symcon_pv_energy': ssState(pvEnergyTotal, 'kWh'),
            'sensor.symcon_solar_forecast_remaining': ssState(
                solarForecastRemaining,
                'kWh'
            ),
            'sensor.symcon_outside_temperature': ssState(
                d.outsideTemperature || 0,
                '°C'
            ),
            'sensor.symcon_grid_import_energy': ssState(d.gridImportEnergyValue || 0, 'kWh'),
            'sensor.symcon_grid_export_energy': ssState(d.gridExportEnergyValue || 0, 'kWh'),
            'sensor.symcon_load_energy': ssState(d.houseEnergy || 0, 'kWh'),
            'sensor.symcon_battery_soc': ssState(Math.round(Number(bat1.soc || 0)), '%'),
            'sensor.symcon_battery_power': ssState(Number(bat1.value || 0), 'W'),
            'sensor.symcon_battery_current': ssState(Number(bat1.current || 0), 'A'),
            'sensor.symcon_battery_voltage': ssState(Number(bat1.voltage || 0), 'V'),
            'sensor.symcon_battery_temperature': ssState(
                Number(bat1.temperature || 0),
                '°C'
            ),
            'sensor.symcon_battery_status': {
                state: String(bat1.statusText || ''),
                attributes: {}
            },
            'sensor.symcon_battery2_soc': ssState(Math.round(Number(bat2.soc || 0)), '%'),
            'sensor.symcon_battery2_power': ssState(Number(bat2.value || 0), 'W'),
            'sensor.symcon_battery2_current': ssState(Number(bat2.current || 0), 'A'),
            'sensor.symcon_battery2_voltage': ssState(Number(bat2.voltage || 0), 'V'),
            'sensor.symcon_battery2_temperature': ssState(
                Number(bat2.temperature || 0),
                '°C'
            ),
            'sensor.symcon_battery2_status': {
                state: String(bat2.statusText || ''),
                attributes: {}
            },
            'sensor.symcon_battery_charge_energy': ssState(bat1.chargeEnergy || 0, 'kWh'),
            'sensor.symcon_battery_discharge_energy': ssState(bat1.dischargeEnergy || 0, 'kWh'),
            'sensor.symcon_battery2_charge_energy': ssState(bat2.chargeEnergy || 0, 'kWh'),
            'sensor.symcon_battery2_discharge_energy': ssState(bat2.dischargeEnergy || 0, 'kWh')
        };
        activePvs.forEach((pv, i) => {
            const stringNo = i + 1;

            states[`sensor.symcon_pv${stringNo}`] =
                ssState(pv.value || 0, 'W');

            states[`sensor.symcon_pv${stringNo}_voltage`] =
                ssState(pv.voltage || 0, 'V');

            states[`sensor.symcon_pv${stringNo}_current`] =
                ssState(pv.current || 0, 'A');
        });
        activeGroups.forEach((group, i) => {
            states[`sensor.symcon_branch${i + 1}`] = ssState(group.value || 0, 'W');
            states[`sensor.symcon_branch${i + 1}_daily`] = ssState(group.dailyValue || 0, 'kWh');
        });

        const auxTotalPower = auxGroups.reduce(
            (sum, group) => sum + Math.max(Number(group.value || 0), 0),
            0
        );
        const auxTotalEnergy = auxGroups.reduce(
            (sum, group) => sum + (
                group.hasDaily
                    ? Math.max(Number(group.dailyValue || 0), 0)
                    : 0
            ),
            0
        );

        states['sensor.symcon_aux_total'] = ssState(auxTotalPower, 'W');
        states['sensor.symcon_aux_energy'] = ssState(auxTotalEnergy, 'kWh');
        states['sensor.symcon_aux1'] = ssState(auxGroups[0]?.value || 0, 'W');
        states['sensor.symcon_aux2'] = ssState(auxGroups[1]?.value || 0, 'W');
        states['sensor.symcon_aux1_extra'] = ssState(
            auxGroups[0]?.hasSoc
                ? Number.parseFloat(String(auxGroups[0]?.socText || '0').replace(',', '.')) || 0
                : 0,
            '%'
        );
        states['sensor.symcon_aux2_extra'] = ssState(
            auxGroups[1]?.hasSoc
                ? Number.parseFloat(String(auxGroups[1]?.socText || '0').replace(',', '.')) || 0
                : 0,
            '%'
        );

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

    async function applySunsynkViewOverrides(card, d = null) {
        // Keine Geometrie und keine Wechselrichterwerte nachträglich verändern.
        // Die WR-Leistung wird ausschließlich über inverter_power_175 von der
        // Originalkarte dargestellt. Hier werden nur Verbraucherfarben korrigiert.
        if (!card) return;
        await card.updateComplete;

        applyAdditionalLoadColours(card);
        alignConsumerNamesToPowerBoxes(card);
        applyAdditionalLoadWattColourByGeometry(card);
        applyHouseLoadWattColour(card, d);
        applyDynamicHouseSourceIcon(card, d);
        applyInverterVisualColour(card, d);
        showInverterPowerAboveVoltages(card, d);
        applyConfiguredBatteryStatus(card, d);
        applyAuxVisualOverrides(card, d);

        // Einige Versionen der Originalkarte erzeugen die inneren SVG-Knoten
        // erst nach dem updateComplete des äußeren Elements. Kurze Wiederholungen
        // stellen sicher, dass die Verbraucherfarben anschließend gesetzt werden.
        [0, 80, 250, 600, 1200].forEach(delay => {
            setTimeout(() => {
                alignConsumerNamesToPowerBoxes(card);
                applyAdditionalLoadWattColourByGeometry(card);
                applyHouseLoadWattColour(
                    card,
                    card.__symconLastData || d
                );
                applyDynamicHouseSourceIcon(
                    card,
                    card.__symconLastData || d
                );
                applyInverterVisualColour(
                    card,
                    card.__symconLastData || d
                );
                showInverterPowerAboveVoltages(
                    card,
                    card.__symconLastData || d
                );
                applyConfiguredBatteryStatus(
                    card,
                    card.__symconLastData || d
                );
                applyAuxVisualOverrides(
                    card,
                    card.__symconLastData || d
                );
            }, delay);
        });

        // Lit rendert bei jeder neuen hass-Zuweisung Teile des Shadow-DOM neu.
        // Deshalb die rein optischen Korrekturen nach jedem Render erneut anwenden.
        if (!card.__symconVisualObserver && card.shadowRoot) {
            let scheduled = false;
            card.__symconVisualObserver = new MutationObserver(() => {
                if (scheduled) return;
                scheduled = true;
                requestAnimationFrame(() => {
                    scheduled = false;
                    applyAdditionalLoadColours(card);
                    alignConsumerNamesToPowerBoxes(card);
                    applyAdditionalLoadWattColourByGeometry(card);
                    applyHouseLoadWattColour(
                        card,
                        card.__symconLastData || d
                    );
                    applyDynamicHouseSourceIcon(
                        card,
                        card.__symconLastData || d
                    );
                    applyInverterVisualColour(
                        card,
                        card.__symconLastData || d
                    );
                    showInverterPowerAboveVoltages(
                        card,
                        card.__symconLastData || d
                    );
                    applyConfiguredBatteryStatus(
                        card,
                        card.__symconLastData || d
                    );
                    applyAuxVisualOverrides(
                        card,
                        card.__symconLastData || d
                    );
                });
            });
            card.__symconVisualObserver.observe(card.shadowRoot, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class', 'fill', 'stroke']
            });
        }
    }

    function findInOpenShadowRoots(root, selector) {
        if (!root) return null;

        const direct = root.querySelector ? root.querySelector(selector) : null;
        if (direct) return direct;

        const elements = root.querySelectorAll ? root.querySelectorAll('*') : [];
        for (const element of elements) {
            if (element.shadowRoot) {
                const found = findInOpenShadowRoots(element.shadowRoot, selector);
                if (found) return found;
            }
        }

        return null;
    }

    function getOpenShadowRoots(root) {
        const roots = [];
        const visit = current => {
            if (!current || roots.includes(current)) return;
            roots.push(current);
            current.querySelectorAll?.('*').forEach(element => {
                if (element.shadowRoot) visit(element.shadowRoot);
            });
        };
        visit(root);
        return roots;
    }



    function applyAuxVisualOverrides(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        const configuredAux = Array.isArray(d.groups)
            ? d.groups
                .filter(group => group?.hasPower && group?.isAux === true)
                .map(group => ({
                    ...group,
                    value: Math.max(Number(group.value || 0), 0),
                    displayThreshold: Math.max(
                        Number(group.displayThreshold || 0),
                        0
                    )
                }))
                .filter(group =>
                    group.displayThreshold <= 0 ||
                    group.value >= group.displayThreshold
                )
                .slice(0, 2)
            : [];

        if (configuredAux.length === 0) return;

        const roots = getOpenShadowRoots(card.shadowRoot);
        const findNode = selector => {
            for (const root of roots) {
                const node = root.querySelector?.(selector);
                if (node) return node;
            }
            return null;
        };

        const cleanSoc = group => {
            if (!group?.hasSoc) return '';

            // Der Text ist bereits durch IP-Symcon formatiert. Dadurch bleiben
            // Stringwerte, Profiltexte sowie Präfix und Suffix unverändert.
            return String(group.socText || '').trim();
        };

        const setLabel = (selector, group) => {
            const node = findNode(selector);
            if (!node || !group) return;

            const name = String(group.name || '').trim();
            const soc = cleanSoc(group);
            const wanted = [name, soc].filter(Boolean).join(' · ');

            if (
                wanted !== '' &&
                String(node.textContent || '').trim() !== wanted
            ) {
                node.textContent = wanted;
            }
        };

        if (configuredAux.length === 1) {
            setLabel('#aux_one', configuredAux[0]);

            // Die Card enthält für den Haupt-AUX mehrere alternative
            // Originalsymbole. Bei einem frei konfigurierten Verbrauchericon
            // dürfen diese nicht zusätzlich sichtbar bleiben.
            [
                '#aux_aux_default',
                '#aux_aux_generator',
                '#aux_aux_oven',
                '#aux_aux_boiler',
                '#aux_aux_ac',
                '#aux_aux_pump',
                '#aux_inverter'
            ].forEach(selector => {
                const node = findNode(selector);
                if (!node) return;

                if (node.style?.display !== 'none') {
                    node.style?.setProperty(
                        'display',
                        'none',
                        'important'
                    );
                }
            });
        } else {
            setLabel('#aux_load1', configuredAux[0]);
            setLabel('#aux_load2', configuredAux[1]);

            // AUX1 und AUX2 werden vollständig ohne Icon dargestellt.
            // Es wird kein ungültiger Icon-Name an die Card übergeben.
            [
                '.aux-small-icon-1',
                '.aux-small-icon-2',
                '#aux_load1_icon',
                '#aux_load2_icon',
                '[id*="aux_load1"][class*="icon"]',
                '[id*="aux_load2"][class*="icon"]'
            ].forEach(selector => {
                for (const root of roots) {
                    root.querySelectorAll?.(selector).forEach(node => {
                        node.style?.setProperty(
                            'display',
                            'none',
                            'important'
                        );
                        node.style?.setProperty(
                            'visibility',
                            'hidden',
                            'important'
                        );
                        node.setAttribute?.('display', 'none');
                        node.setAttribute?.('visibility', 'hidden');
                    });
                }
            });

            // Der SOC steht jetzt direkt hinter dem Namen und soll nicht
            // nochmals als separate Zusatzzeile erscheinen.
            [
                '#aux_load1_extra',
                '#aux_load2_extra'
            ].forEach(selector => {
                const node = findNode(selector);
                if (!node) return;

                if (node.style?.display !== 'none') {
                    node.style?.setProperty(
                        'display',
                        'none',
                        'important'
                    );
                }
            });
        }
    }


    function applyConfiguredBatteryStatus(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        const batteries = Array.isArray(d.batteries)
            ? d.batteries
            : [];

        const statusDefinitions = [
            {
                battery: batteries[0],
                selectors: [
                    '#battery_state_msg',
                    '[id="battery_state_msg"]'
                ]
            },
            {
                battery: batteries[1],
                selectors: [
                    '#battery2_state_msg',
                    '[id="battery2_state_msg"]'
                ]
            }
        ];

        const roots = getOpenShadowRoots(card.shadowRoot);

        statusDefinitions.forEach(definition => {
            const battery = definition.battery;
            if (!battery || battery.hasStatus !== true) return;

            const statusText = String(battery.statusText || '').trim();
            if (statusText === '') return;

            for (const root of roots) {
                const node = root.querySelector?.(
                    definition.selectors.join(',')
                );

                if (!node) continue;

                // Nur schreiben, wenn sich der Text tatsächlich unterscheidet.
                // Dadurch entsteht mit dem vorhandenen visuellen Observer
                // keine Render- oder Mutation-Schleife.
                if (String(node.textContent || '').trim() !== statusText) {
                    node.textContent = statusText;
                }

                node.removeAttribute?.('display');
                node.style?.setProperty('display', 'inline', 'important');
                node.style?.setProperty('visibility', 'visible', 'important');
                node.style?.setProperty('opacity', '1', 'important');
                break;
            }
        });
    }


    function showInverterPowerAboveVoltages(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        const roots = getOpenShadowRoots(card.shadowRoot);
        const isFullLayout =
            currentTechnicalLayout === 'full' ||
            currentTechnicalLayout === 'full-wide';

        // In Full/Full Wide darf diese zusätzliche Anzeige nicht erscheinen.
        // Ein aus Compact/Lite vorhandenes Overlay wird beim Umschalten entfernt.
        if (isFullLayout) {
            for (const root of roots) {
                root.querySelectorAll?.(
                    '#symcon_inverter_power_overlay'
                ).forEach(node => node.remove());
            }
            return;
        }

        const available =
            entityAvailable(d, 'inverterPower') ||
            d.inverterPowerAvailable === true;

        if (!available) return;

        const rawValue = Number(d.inverterPower);
        const value = Number.isFinite(rawValue) ? rawValue : 0;
        const valueText =
            `${Math.round(value).toLocaleString('de-DE')} W`;

        for (const root of roots) {
            const voltageSelectors = [
                '#inverter_voltage_154',
                '[id="inverter_voltage_154"]',
                '#inverter-voltage-154',
                '[id*="inverter_voltage_154"]',
                '[id*="inverter-voltage-154"]'
            ];

            const voltageContainer =
                root.querySelector?.(voltageSelectors.join(',')) || null;

            if (!voltageContainer) {
                continue;
            }

            let voltageText = voltageContainer;
            const tag = String(voltageText.tagName || '').toLowerCase();

            if (tag !== 'text' && tag !== 'tspan') {
                voltageText = Array.from(
                    voltageContainer.querySelectorAll?.('text, tspan') || []
                ).find(node =>
                    /V$/i.test(String(node.textContent || '').trim())
                ) || null;
            }

            if (!voltageText) {
                continue;
            }

            const template =
                String(voltageText.tagName || '').toLowerCase() === 'tspan'
                    ? (voltageText.closest?.('text') || voltageText)
                    : voltageText;

            const parent = template.parentNode;
            if (!parent) {
                continue;
            }

            let powerNode =
                parent.querySelector?.('#symcon_inverter_power_overlay') ||
                null;

            if (!powerNode) {
                powerNode = template.cloneNode(true);
                powerNode.id = 'symcon_inverter_power_overlay';
                parent.insertBefore(powerNode, template);
            }

            powerNode.textContent = valueText;
            powerNode.removeAttribute?.('display');
            powerNode.removeAttribute?.('visibility');
            powerNode.removeAttribute?.('opacity');
            powerNode.removeAttribute?.('hidden');
            powerNode.style?.setProperty('display', 'inline', 'important');
            powerNode.style?.setProperty('visibility', 'visible', 'important');
            powerNode.style?.setProperty('opacity', '1', 'important');

            // Direkt oberhalb der ersten Spannungszeile positionieren.
            // Die vorhandenen Spannungs- und Stromwerte werden nicht verändert.
            const x = Number(template.getAttribute?.('x'));
            const y = Number(template.getAttribute?.('y'));

            if (Number.isFinite(x)) {
                powerNode.setAttribute('x', String(x));
            }

            if (Number.isFinite(y)) {
                // Gegenüber v51 um 4 px tiefer, damit der Abstand zur
                // ersten Spannungszeile dem Abstand Spannung → Strom entspricht.
                powerNode.setAttribute('y', String(y - 12));
                powerNode.removeAttribute?.('transform');
            } else {
                const originalTransform =
                    template.getAttribute?.('transform') || '';

                powerNode.setAttribute(
                    'transform',
                    `${originalTransform} translate(0 -12)`.trim()
                );
            }

            return;
        }
    }

    function applyInverterVisualColour(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        const inverterColour =
            d.houseColors?.inverter ||
            d.colors?.inverter ||
            AC.inverter;

        const roots = getOpenShadowRoots(card.shadowRoot);

        const colourText = node => {
            if (!node) return;

            node.setAttribute?.('fill', inverterColour);
            node.setAttribute?.('color', inverterColour);
            node.style?.setProperty('fill', inverterColour, 'important');
            node.style?.setProperty('color', inverterColour, 'important');

            node.querySelectorAll?.('tspan').forEach(tspan => {
                tspan.setAttribute?.('fill', inverterColour);
                tspan.style?.setProperty(
                    'fill',
                    inverterColour,
                    'important'
                );
                tspan.style?.setProperty(
                    'color',
                    inverterColour,
                    'important'
                );
            });
        };

        for (const root of roots) {
            /*
             * Ausschließlich echte Wechselrichter-Elemente anfassen.
             * Smartmeter-/Grid-Container werden bewusst nicht mehr über
             * breit gefasste Selektoren wie [id*="inverter"] oder ganze
             * gemeinsame SVG-Gruppen eingefärbt.
             */

            const inverterGroups = new Set();

            [
                '#inverter_main',
                '#inverter-main',
                '#inverter_icon',
                '#inverter-icon',
                '#inverter_data',
                '#inverter-data',
                '#inverter_box',
                '#inverter-box'
            ].forEach(selector => {
                root.querySelectorAll?.(selector).forEach(node => {
                    inverterGroups.add(node);
                });
            });

            // Wechselrichtersymbol.
            inverterGroups.forEach(group => {
                const id = String(group.id || '').toLowerCase();

                if (
                    id.includes('icon') ||
                    id === 'inverter_main' ||
                    id === 'inverter-main'
                ) {
                    group.querySelectorAll?.(
                        'path, rect, polygon, polyline, circle, ellipse'
                    ).forEach(shape => {
                        shape.setAttribute?.('stroke', inverterColour);
                        shape.style?.setProperty(
                            'stroke',
                            inverterColour,
                            'important'
                        );

                        const fill = shape.getAttribute?.('fill');
                        if (
                            fill &&
                            fill !== 'none' &&
                            !String(fill).startsWith('url(')
                        ) {
                            shape.setAttribute?.('fill', inverterColour);
                            shape.style?.setProperty(
                                'fill',
                                inverterColour,
                                'important'
                            );
                        }
                    });
                }
            });

            // Rahmen der echten WR-Datenbox.
            [
                '#inverter_data > rect',
                '#inverter-data > rect',
                '#inverter_box',
                '#inverter-box'
            ].forEach(selector => {
                root.querySelectorAll?.(selector).forEach(shape => {
                    shape.setAttribute?.('stroke', inverterColour);
                    shape.style?.setProperty(
                        'stroke',
                        inverterColour,
                        'important'
                    );
                });
            });

            // Nur eindeutig als Wechselrichterwerte identifizierte Texte.
            // Smartmeter-Spannungen/-Ströme/-Frequenz werden nicht berührt.
            [
                '#inverter_power_175',
                '#inverter-power-175',
                '#symcon_inverter_power_overlay',
                '#inverter_current_164',
                '#inverter-current-164',
                '#inverter_current_L2',
                '#inverter-current-L2',
                '#inverter_current_L3',
                '#inverter-current-L3',
                '#inverter_name',
                '#inverter-name',
                '#inverter_label',
                '#inverter-label'
            ].forEach(selector => {
                root.querySelectorAll?.(selector).forEach(node => {
                    colourText(node);
                });
            });

            // Falls die WR-Leistung direkt oberhalb der WR-Ströme als
            // eigenes Symcon-Overlay erzeugt wurde, ebenfalls einfärben.
            root.querySelectorAll?.(
                '#symcon_inverter_power_overlay, ' +
                '#symcon_inverter_power_fixed'
            ).forEach(node => {
                colourText(node);
            });
        }
    }

    function applyDynamicHouseSourceIcon(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        const pvs = Array.isArray(d.pvs) ? d.pvs : [];
        const batteries = Array.isArray(d.batteries) ? d.batteries : [];

        const solarPower = pvs.reduce(
            (sum, pv) => sum + Math.max(Number(pv.value || 0), 0),
            0
        );

        // Im Modul bedeutet positive Batterieleistung: Entladen zum Haus.
        const batteryPower = batteries.reduce(
            (sum, battery) =>
                sum + Math.max(Number(battery.value || 0), 0),
            0
        );

        // Netz positiv = Bezug, negativ = Einspeisung.
        const gridPower = Math.max(Number(d.grid || 0), 0);

        const sources = [
            { type: 'solar', power: solarPower },
            { type: 'battery', power: batteryPower },
            { type: 'grid', power: gridPower }
        ].sort((a, b) => b.power - a.power);

        const source = sources[0].power > 0
            ? sources[0].type
            : 'normal';

        // Originale Pfade der Sunsynk-Karte.
        const paths = {
            normal:
                'm15 13l-4 4v-3H2v-2h9V9l4 4M5 20v-4h2v2h10v-7.81l-5-4.5L7.21 10H4.22L12 3l10 9h-3v8H5Z',
            battery:
                'M15 9h1V7.5h4V9h1c.55 0 1 .45 1 1v11c0 .55-.45 1-1 1h-6c-.55 0-1-.45-1-1V10c0-.55.45-1 1-1m1 2v3h4v-3h-4m-4-5.31l-5 4.5V18h5v2H5v-8H2l10-9l2.78 2.5H14v1.67l-.24.1L12 5.69Z',
            grid:
                'M5 20v-8H2l10-9l10 9h-3v8zm7-14.31l-5 4.5V18h10v-7.81zM11.5 18v-4H9l3.5-7v4H15z',
            solar:
                'M11.6 3.45zM18.25 19.6v-7.6h2.85L11.6 3.45 2.1 12h2.85v7.6zM11.6 6.015l4.75 4.275V17.7H6.85v-7.41zM6.58 2.8v1.42L8 3.508zm-.4 2.4L5.2 6.184l1.5.5zM2.8 6.58 3.508 8l.712-1.42zM6 2.8H2.8v3.2c.228.068.468.1.708.1 1.432.004 2.596-1.16 2.6-2.6-.004-.236-.04-.472-.108-.7M12.5 3.844l2.25 2.026.5-.5-2.24-2.04zM17.71 8.53 18.2 8.04 15.76 5.84 15.26 6.34ZM20.52 11.09l.48-.49-2.31-2.14-.5.5z M18.1299 5.1169 17.318 4.6482l2.4492-1.6171-.75 1.299.8119.4687-2.4492 1.6171z'
        };

        const knownPaths = new Set(Object.values(paths));
        const targetPath = paths[source] || paths.normal;
        const roots = getOpenShadowRoots(card.shadowRoot);

        for (const root of roots) {
            root.querySelectorAll?.('path[d]').forEach(path => {
                const current = String(path.getAttribute('d') || '').trim();

                // Nur das originale Sunsynk-Haussymbol anfassen.
                if (!knownPaths.has(current)) {
                    return;
                }

                path.setAttribute('d', targetPath);
                path.setAttribute('data-symcon-house-source', source);
            });
        }
    }

    function applyHouseLoadWattColour(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        const houseColour = dominantHouseSourceColour(
            Number(d.grid || 0),
            Array.isArray(d.pvs) ? d.pvs : [],
            Array.isArray(d.batteries) ? d.batteries : []
        );

        const roots = getOpenShadowRoots(card.shadowRoot);

        const colourText = node => {
            if (!node) return;

            node.setAttribute?.('fill', houseColour);
            node.setAttribute?.('color', houseColour);
            node.style?.setProperty('fill', houseColour, 'important');
            node.style?.setProperty('color', houseColour, 'important');

            node.querySelectorAll?.('tspan').forEach(tspan => {
                tspan.setAttribute?.('fill', houseColour);
                tspan.style?.setProperty(
                    'fill',
                    houseColour,
                    'important'
                );
                tspan.style?.setProperty(
                    'color',
                    houseColour,
                    'important'
                );
            });
        };

        for (const root of roots) {
            const houseNodes = new Set();

            // Bekannte IDs der Hauptlast. Zusätzliche Verbraucher load1 ... load6
            // werden ausdrücklich nicht erfasst.
            [
                '#essential_power',
                '#essential-power',
                '#essential_load',
                '#essential-load',
                '#essential_name',
                '#essential-name',
                '#load_power',
                '#load-power',
                '#load_value',
                '#load-value',
                '#load_name',
                '#load-name',
                '#house_power',
                '#house-power',
                '#house_load',
                '#house-load'
            ].forEach(selector => {
                root.querySelectorAll?.(selector).forEach(node => {
                    houseNodes.add(node);
                    node.querySelectorAll?.('text, tspan').forEach(child => {
                        houseNodes.add(child);
                    });
                });
            });

            // Die sichtbare Bezeichnung „Hausverbrauch“ ist der zuverlässigste
            // layoutunabhängige Anker. Nur ihre direkte SVG-Gruppe wird geprüft.
            root.querySelectorAll?.('text, tspan').forEach(labelNode => {
                const shown = String(labelNode.textContent || '').trim();

                if (shown !== 'Hausverbrauch') {
                    return;
                }

                houseNodes.add(labelNode);

                const group = labelNode.closest?.('g');
                if (!group) {
                    return;
                }

                const groupId = String(group.id || '').toLowerCase();

                if (
                    /(?:load|ess)[-_]?[1-6]/.test(groupId) ||
                    groupId.includes('aux') ||
                    groupId.includes('nonessential')
                ) {
                    return;
                }

                group.querySelectorAll?.('text, tspan').forEach(node => {
                    const value = String(node.textContent || '').trim();

                    if (
                        value === 'Hausverbrauch' ||
                        /[-+]?\d[\d.,'’\s]*\s*(?:W|kW)$/i.test(value)
                    ) {
                        houseNodes.add(node);
                    }
                });
            });

            houseNodes.forEach(node => {
                const tag = String(node.tagName || '').toLowerCase();

                if (tag !== 'text' && tag !== 'tspan') {
                    return;
                }

                const shown = String(node.textContent || '').trim();

                if (
                    shown === 'Hausverbrauch' ||
                    /[-+]?\d[\d.,'’\s]*\s*(?:W|kW)$/i.test(shown)
                ) {
                    colourText(node);
                }
            });
        }
    }

    function alignConsumerNamesToPowerBoxes(card) {
        if (!card || !card.shadowRoot) return;

        const roots = getOpenShadowRoots(card.shadowRoot);

        for (const root of roots) {
            const boxes = [];

            root.querySelectorAll?.(
                'rect[id^="es-load"], rect[id^="ess-load"]'
            ).forEach(rect => {
                try {
                    const box = rect.getBBox();

                    if (box.width > 0 && box.height > 0) {
                        boxes.push({
                            node: rect,
                            box,
                            centerX: box.x + (box.width / 2)
                        });
                    }
                } catch (_) {
                    // Unsichtbare oder noch nicht aufgebaute SVG-Knoten.
                }
            });

            if (!boxes.length) {
                continue;
            }

            const consumerNames = new Set(
                Array.isArray(window.__symconVisibleConsumerNames)
                    ? window.__symconVisibleConsumerNames
                    : []
            );

            if (!consumerNames.size) {
                continue;
            }

            root.querySelectorAll?.('text').forEach(textNode => {
                const value = String(
                    textNode.textContent || ''
                ).trim();

                // Ausschließlich exakt konfigurierte Verbrauchernamen
                // zentrieren. PV-Stringwerte wie V/A/W sowie sämtliche
                // anderen Texte bleiben an ihrer Originalposition.
                if (!consumerNames.has(value)) {
                    return;
                }

                let textBox;

                try {
                    textBox = textNode.getBBox();
                } catch (_) {
                    return;
                }

                const textCenterX =
                    textBox.x + (textBox.width / 2);
                const textCenterY =
                    textBox.y + (textBox.height / 2);

                let closest = null;
                let closestDistance =
                    Number.POSITIVE_INFINITY;

                boxes.forEach(candidate => {
                    const box = candidate.box;

                    // Nur Beschriftungen in einem sinnvollen Bereich
                    // unmittelbar über oder unter der Verbraucherbox.
                    const verticalDistance = Math.min(
                        Math.abs(textCenterY - box.y),
                        Math.abs(
                            textCenterY -
                            (box.y + box.height)
                        )
                    );

                    const horizontalDistance = Math.abs(
                        textCenterX - candidate.centerX
                    );

                    if (
                        verticalDistance > 55 ||
                        horizontalDistance > 90
                    ) {
                        return;
                    }

                    const distance =
                        verticalDistance +
                        horizontalDistance;

                    if (distance < closestDistance) {
                        closestDistance = distance;
                        closest = candidate;
                    }
                });

                if (!closest) {
                    return;
                }

                textNode.setAttribute(
                    'x',
                    String(closest.centerX)
                );
                textNode.setAttribute(
                    'text-anchor',
                    'middle'
                );
                textNode.style?.setProperty(
                    'text-anchor',
                    'middle',
                    'important'
                );

                textNode.querySelectorAll?.('tspan').forEach(tspan => {
                    tspan.setAttribute(
                        'x',
                        String(closest.centerX)
                    );
                    tspan.setAttribute(
                        'text-anchor',
                        'middle'
                    );
                    tspan.style?.setProperty(
                        'text-anchor',
                        'middle',
                        'important'
                    );
                });
            });
        }
    }

    function applyAdditionalLoadWattColourByGeometry(card) {
        if (!card || !card.shadowRoot) return;

        const consumerColour = AC.room;
        const roots = getOpenShadowRoots(card.shadowRoot);

        for (const root of roots) {
            const boxes = [];

            // Je nach Layout/Version heißen die Verbraucherboxen es-load1 ... es-load6.
            // Wir verwenden zusätzlich alle passenden Rechtecke, damit auch doppelte IDs
            // und leicht abweichende DOM-Strukturen der Originalkarte erfasst werden.
            root.querySelectorAll?.('rect[id^="es-load"], rect[id^="ess-load"]').forEach(rect => {
                try {
                    const box = rect.getBBox();
                    if (box && box.width > 0 && box.height > 0) boxes.push(box);
                } catch (_) {}
            });

            const wattNodes = new Set();
            root.querySelectorAll?.('[id^="ess_load"][id$="_value"], [id^="ess-load"][id$="-value"]').forEach(node => wattNodes.add(node));

            // Fallback: Die Watttexte anhand ihrer tatsächlichen Position innerhalb
            // einer Verbraucherbox erkennen. Damit sind wir nicht mehr von den IDs
            // der jeweiligen Karten-Version abhängig.
            root.querySelectorAll?.('text, tspan').forEach(node => {
                const text = String(node.textContent || '').trim();
                if (!/(?:^|\s)[−+\-]?\d[\d.,'’\s]*\s*(?:W|kW)$/i.test(text)) return;

                try {
                    const b = node.getBBox();
                    const cx = b.x + b.width / 2;
                    const cy = b.y + b.height / 2;
                    const inside = boxes.some(box =>
                        cx >= box.x - 2 && cx <= box.x + box.width + 2 &&
                        cy >= box.y - 2 && cy <= box.y + box.height + 2
                    );
                    if (inside) wattNodes.add(node);
                } catch (_) {}
            });

            wattNodes.forEach(node => {
                node.setAttribute?.('fill', consumerColour);
                node.setAttribute?.('color', consumerColour);
                node.setAttribute?.('font-weight', '400');
                node.style?.setProperty('fill', consumerColour, 'important');
                node.style?.setProperty('color', consumerColour, 'important');
                node.style?.setProperty('font-weight', '400', 'important');
                node.style?.setProperty('font-variation-settings', '"wght" 400', 'important');

                // Manche Varianten schreiben die sichtbare Farbe auf ein inneres tspan.
                node.querySelectorAll?.('tspan').forEach(tspan => {
                    tspan.setAttribute?.('fill', consumerColour);
                    tspan.style?.setProperty('fill', consumerColour, 'important');
                    tspan.style?.setProperty('color', consumerColour, 'important');
                    tspan.style?.setProperty('font-weight', '400', 'important');
                });
            });
        }
    }

    function applyAdditionalLoadColours(card) {
        if (!card || !card.shadowRoot) return;

        const roots = getOpenShadowRoots(card.shadowRoot);
        const consumerColour = AC.room; // Konfiguration „Verbraucher“

        // Die zusätzlichen Verbraucher load1 ... load6 behalten ihre
        // konfigurierte Farbe „Verbraucher“. Hausverbrauch und
        // Haussymbol werden separat dynamisch nach Hauptquelle eingefärbt.
        for (const root of roots) {
            // Nur die Watt-Leistungswerte in den Verbraucherboxen dauerhaft
            // auf die konfigurierte Farbe „Verbraucher“ festlegen.
            // Die Original-Card setzt diese Texte bei Aktualisierungen erneut,
            // deshalb erfolgt die Korrektur zusätzlich über eine lokale CSS-Regel.
            if (!root.getElementById?.('symcon-additional-load-watt-colours')) {
                const wattStyle = document.createElement('style');
                wattStyle.id = 'symcon-additional-load-watt-colours';
                wattStyle.textContent = `
                    #ess_load1_value,
                    #ess_load2_value,
                    #ess_load3_value,
                    #ess_load4_value,
                    #ess_load5_value,
                    #ess_load6_value {
                        fill: ${consumerColour} !important;
                        color: ${consumerColour} !important;
                        font-weight: 400 !important;
                        font-variation-settings: "wght" 400 !important;
                    }
                `;
                root.appendChild?.(wattStyle);
            }

            for (let i = 1; i <= 6; i++) {
                const selectors = [
                    `[id="es-load${i}"]`,
                    `[id="ess-load${i}"]`,
                    `[id="ess_load${i}_value"]`,
                    `[id="ess_load${i}_value_extra"]`,
                    `[id^="ess_load${i}_"]`,
                    `[id^="ess-load${i}-"]`,
                    `[id^="es-load${i}-"]`
                ];

                const nodes = new Set();
                root.querySelectorAll?.(selectors.join(',')).forEach(node => {
                    nodes.add(node);
                    node.querySelectorAll?.('*').forEach(child => nodes.add(child));
                });

                nodes.forEach(node => {
                    const tag = String(node.tagName || '').toLowerCase();
                    const id = String(node.id || '');

                    // Die Wertebox bleibt ungefüllt; nur ihr Rahmen bekommt
                    // die Farbe „Verbraucher“.
                    if (tag === 'rect' && id === `es-load${i}`) {
                        node.setAttribute?.('fill', 'none');
                        node.style?.setProperty('fill', 'none', 'important');
                        node.setAttribute?.('stroke', consumerColour);
                        node.style?.setProperty('stroke', consumerColour, 'important');
                        return;
                    }

                    // Namen, Leistung und kWh erhalten dieselbe Verbraucherfarbe.
                    if (tag === 'text' || tag === 'tspan' || id === `ess-load${i}` || id.startsWith(`ess_load${i}_`)) {
                        node.setAttribute?.('fill', consumerColour);
                        node.style?.setProperty('fill', consumerColour, 'important');
                        node.style?.setProperty('color', consumerColour, 'important');
                        node.style?.setProperty('font-weight', '400', 'important');
                        node.style?.setProperty('font-variation-settings', '"wght" 400', 'important');
                        node.setAttribute?.('font-weight', '400');
                        return;
                    }

                    // Icons und zugehörige grafische Elemente der zusätzlichen
                    // Verbraucher ebenfalls einheitlich einfärben.
                    if (['path', 'polygon', 'polyline', 'line', 'circle', 'ellipse'].includes(tag)) {
                        node.setAttribute?.('stroke', consumerColour);
                        node.style?.setProperty('stroke', consumerColour, 'important');

                        if (!node.classList?.contains('anim-line') && tag !== 'line' && tag !== 'polyline') {
                            node.setAttribute?.('fill', consumerColour);
                            node.style?.setProperty('fill', consumerColour, 'important');
                        }
                    }
                });
            }
        }
    }

    function updateSunsynkWallboxAuxInfo() {
        // Fahrzeug-Zusatzdaten werden nicht als frei schwebende Box über
        // die Originalgrafik gelegt. AUX-Name, Leistung und Energie stellt
        // die Sunsynk-Karte selbst dar.
    }

    function clampPercent(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return 0;
        }

        return Math.max(0, Math.min(100, Math.round(number)));
    }

    function applySunsynkRatios(
        card,
        d,
        grid,
        haus,
        pvs,
        batteries,
        attempt = 0
    ) {
        if (!card || !d) {
            return;
        }

        const mode = ['power', 'energy', 'no'].includes(
            d.autarkyCalculationMode
        )
            ? d.autarkyCalculationMode
            : 'energy';

        if (mode === 'no') {
            return;
        }

        const root = card.shadowRoot;
        if (!root) {
            if (attempt < 20) {
                setTimeout(
                    () => applySunsynkRatios(
                        card,
                        d,
                        grid,
                        haus,
                        pvs,
                        batteries,
                        attempt + 1
                    ),
                    50
                );
            }
            return;
        }

        let autarky = 0;
        let selfConsumption = 0;
        let valueSuffix = mode === 'power' ? 'p' : 'e';

        if (mode === 'power') {
            const housePower = Math.max(Number(haus || 0), 0);
            const gridPower = Number(grid || 0);
            const gridImportPower = Math.max(gridPower, 0);
            const gridExportPower = Math.max(-gridPower, 0);
            const pvPower = (Array.isArray(pvs) ? pvs : []).reduce(
                (sum, pv) => sum + Math.max(Number(pv?.value || 0), 0),
                0
            );

            // Momentane Autarkie: Anteil des Hausverbrauchs, der aktuell
            // nicht aus dem Netz bezogen wird.
            autarky = housePower > 0
                ? clampPercent(
                    ((housePower - gridImportPower) / housePower) * 100
                )
                : 0;

            const batteryDischargePower = (
                Array.isArray(batteries) ? batteries : []
            ).reduce(
                (sum, battery) =>
                    sum + Math.max(Number(battery?.value || 0), 0),
                0
            );
            const ownPower = pvPower + batteryDischargePower;

            // Momentaner Eigenverbrauch im Hybridsystem: PV-Leistung plus
            // Batterieentladung gelten als eigene Leistung. Nur die aktuelle
            // Netzeinspeisung vermindert den Eigenverbrauch.
            selfConsumption = ownPower > 0
                ? clampPercent(
                    ((ownPower - gridExportPower) / ownPower) * 100
                )
                : 0;
        } else {
            const houseEnergy = Number(d.houseEnergy || 0);
            const gridImportEnergy = Number(d.gridImportEnergyValue || 0);
            const gridExportEnergy = Number(d.gridExportEnergyValue || 0);
            const pvEnergy = Number(d.inverterDailyEnergy || 0);

            // Tagesautarkie: Anteil des Tagesverbrauchs ohne Netzbezug.
            autarky = houseEnergy > 0
                ? clampPercent(
                    ((houseEnergy - Math.max(gridImportEnergy, 0)) /
                        houseEnergy) * 100
                )
                : 0;

            // Tages-Eigenverbrauch:
            // Anteil der Wechselrichter-Tagesenergie, der nicht ins Netz
            // eingespeist wurde. Die Batterieentladung wird nicht nochmals
            // addiert, da sie keine zusätzliche Energieerzeugung darstellt.
            const ownEnergy = pvEnergy;

            selfConsumption = ownEnergy > 0
                ? clampPercent(
                    ((ownEnergy - Math.max(gridExportEnergy, 0)) /
                        ownEnergy) * 100
                )
                : 0;
        }

        // Optional konfigurierte Prozentvariablen haben Vorrang vor
        // der internen Berechnung. Ohne Auswahl bleibt das bisherige
        // Berechnungsverhalten vollständig erhalten.
        if (d.autarkyVariableAvailable) {
            autarky = clampPercent(
                Number(d.autarkyVariableValue || 0)
            );
        }

        if (d.selfConsumptionVariableAvailable) {
            selfConsumption = clampPercent(
                Number(d.selfConsumptionVariableValue || 0)
            );
        }

        const autarkyValue = root.getElementById(
            `autarky${valueSuffix}_value`
        );
        const ratioValue = root.getElementById(
            `ratio${valueSuffix}_value`
        );
        const autarkyLabel = root.getElementById('autarky');
        const ratioLabel = root.getElementById('ratio');

        if (autarkyValue) {
            autarkyValue.textContent = `${autarky}%`;
        }
        if (ratioValue) {
            ratioValue.textContent = `${selfConsumption}%`;
        }
        if (autarkyLabel) {
            autarkyLabel.textContent = 'Autarkie';
        }
        if (ratioLabel) {
            ratioLabel.textContent = 'Eigenverbrauch';
        }

        // Lit kann unmittelbar nach unserem Zugriff nochmals rendern.
        if ((!autarkyValue || !ratioValue) && attempt < 20) {
            setTimeout(
                () => applySunsynkRatios(
                    card,
                    d,
                    grid,
                    haus,
                    pvs,
                    batteries,
                    attempt + 1
                ),
                50
            );
        }
    }

    function scheduleSunsynkRatios(
        card,
        d,
        grid,
        haus,
        pvs,
        batteries
    ) {
        [0, 40, 120, 300].forEach(delay => {
            setTimeout(
                () => applySunsynkRatios(
                    card,
                    d,
                    grid,
                    haus,
                    pvs,
                    batteries
                ),
                delay
            );
        });
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
            window.__symconHasWallbox = !!d.hasWallbox;
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
            card.__symconLastData = d;
            await applySunsynkViewOverrides(card, d);
            scheduleSunsynkRatios(card, d, grid, haus, pvs, batteries);
            updateSunsynkWallboxAuxInfo(card, d, wallbox);
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
        currentTechnicalLayout = ['compact', 'compact-wide', 'lite', 'lite-wide', 'full', 'full-wide'].includes(d.technicalLayout) ? d.technicalLayout : 'lite';
        updateTechnicalLayoutButtons();
        if (!sunsynkCard) {
            sunsynkPending = [d, grid, haus, pvs, batteries, wallbox, groups];
            ensureSunsynkCard(d, grid, haus, pvs, batteries, wallbox, groups).catch(() => {});
            return;
        }
        window.__symconHasWallbox = !!d.hasWallbox;
        sunsynkCard.setConfig(createSunsynkConfig(d, pvs, batteries, wallbox, groups));
        sunsynkCard.hass = createSunsynkHass(d, grid, haus, pvs, batteries, wallbox, groups);
        sunsynkCard.__symconLastData = d;
        applySunsynkViewOverrides(sunsynkCard, d);
        scheduleSunsynkRatios(sunsynkCard, d, grid, haus, pvs, batteries);
        updateSunsynkWallboxAuxInfo(sunsynkCard, d, wallbox);
    }

    function buildHouseView(d, grid, haus, pvs, batteries, wallbox) {
        // Die originale Hausgrafik benötigt nur die aufsummierte Leistung.
        // updatePowerFlowCard() summiert die übergebenen Strings selbst.
        // Deshalb werden hier die einzelnen PV-Strings weitergereicht, damit
        // die Infokachel ab 601 px Name, Leistung und Energie je String zeigen
        // kann. Bis 600 px bleibt die Anzeige weiterhin auf die Gesamtsumme
        // reduziert.
        updatePowerFlowCard(
            d,
            grid,
            haus,
            pvs,
            batteries,
            wallbox
        );
    }

    let currentDisplayMode = '__INITIAL_DISPLAY_MODE__';
    let currentTechnicalLayout = 'lite';

    function updateTechnicalLayoutButtons() {
        const layoutButton =
            document.getElementById('technical-layout-button');
        const wideButton =
            document.getElementById('technical-wide-button');

        const baseLayout = currentTechnicalLayout.replace('-wide', '');
        const isWide = currentTechnicalLayout.endsWith('-wide');

        if (layoutButton) {
            const labels = {
                compact: 'C',
                lite: 'L',
                full: 'F'
            };

            layoutButton.textContent = labels[baseLayout] || 'Lite';
            layoutButton.title =
                `Technikansicht: ${labels[baseLayout] || 'Lite'}`;
            layoutButton.setAttribute(
                'aria-label',
                layoutButton.title
            );

            // Der Text ist länger als das bisherige Einzelzeichen.
            layoutButton.style.width = '42px';
        }

        if (wideButton) {
            wideButton.textContent = '⬌';
            wideButton.classList.toggle('active', isWide);
            wideButton.title = isWide
                ? 'Wide-Ansicht ausschalten'
                : 'Wide-Ansicht einschalten';
            wideButton.setAttribute(
                'aria-label',
                wideButton.title
            );
            wideButton.style.width = '42px';
        }
    }

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
        const layoutButton =
            document.getElementById('technical-layout-button');
        const wideButton =
            document.getElementById('technical-wide-button');

        if (layoutButton) {
            layoutButton.style.display =
                house ? 'none' : 'inline-flex';
        }

        if (wideButton) {
            wideButton.style.display =
                house ? 'none' : 'inline-flex';
        }

        updateTechnicalLayoutButtons();
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

    const technicalLayoutButton =
        document.getElementById('technical-layout-button');

    if (technicalLayoutButton) {
        technicalLayoutButton.addEventListener('click', function () {
            const isWide =
                currentTechnicalLayout.endsWith('-wide');
            const currentBase =
                currentTechnicalLayout.replace('-wide', '');
            const order = ['compact', 'lite', 'full'];
            const currentIndex = Math.max(
                0,
                order.indexOf(currentBase)
            );
            const nextBase =
                order[(currentIndex + 1) % order.length];
            const newLayout =
                nextBase + (isWide ? '-wide' : '');

            requestAction('ToggleTechnicalLayout', newLayout);
        });
    }

    const technicalWideButton =
        document.getElementById('technical-wide-button');

    if (technicalWideButton) {
        technicalWideButton.addEventListener('click', function () {
            const isWide =
                currentTechnicalLayout.endsWith('-wide');
            const baseLayout =
                currentTechnicalLayout.replace('-wide', '');
            const newLayout = isWide
                ? baseLayout
                : `${baseLayout}-wide`;

            requestAction('ToggleTechnicalLayout', newLayout);
        });
    }

    updateTechnicalLayoutButtons();
    updateDisplayModeButton();

    // ---------- Layout ----------
    let layoutWidth = 540;

    function updateLayout(groupCount, pvCount, batteryCount, showRightPanel, mode = 'flow', hasWallbox = false) {
        const fitEl = document.getElementById('fit');
        const wrapEl = document.getElementById('wrap');
        const rootEl = document.getElementById('scale-root');

        let graphWidth;

        if (mode === 'house') {
            // Die Hausgrafik besitzt weiterhin ihre feste 900x640-Zeichenfläche.
            graphWidth = 900;
        } else {
            // Die originale Sunsynk-Karte muss exakt dieselbe Breite besitzen
            // wie der Bereich, der anschließend durch fit() skaliert wird.
            // Zuvor blieb #stage immer 1080 px breit, während #fit häufig nur
            // 540 px breit war. Dadurch wurde die rechte Hälfte abgeschnitten
            // und die Karte wirkte in IP-Symcon falsch skaliert.
            graphWidth = currentTechnicalLayout.endsWith('-wide')
                ? 1080
                : 540;
        }

        layoutWidth = graphWidth;

        fitEl.style.width = graphWidth + 'px';
        fitEl.style.flexBasis = graphWidth + 'px';

        wrapEl.style.width = layoutWidth + 'px';
        rootEl.style.width = layoutWidth + 'px';

        // Nur die technische Ansicht an die tatsächlich skalierte Breite
        // angleichen. Die Hausansicht bleibt vollständig unverändert.
        if (stage) {
            stage.style.width = graphWidth + 'px';
        }

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

            // Die Wallbox verwendet überall dieselbe Farbe wie
            // „Weitere Verbraucher“.
            AC.wallbox = AC.room;
            AC.inverter = d.colors.inverter || AC.inverter;
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
        document.documentElement.style.setProperty('--ef-wallbox', AC.room);
        document.documentElement.style.setProperty('--ef-consumer', AC.room);

        const solarMain = document.getElementById('pfc-solar-main');
        const solarInfo = document.getElementById('pfc-info-solar');
        const gridImport = document.getElementById('pfc-grid-import');
        const gridExport = document.getElementById('pfc-grid-export');
        const gridInfo = document.getElementById('pfc-info-grid');
        const wallboxMain = document.getElementById('pfc-wallbox-main');
        const wallboxInfo = document.getElementById('pfc-info-wallbox');
        const homeMain = document.getElementById('pfc-home-main');

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
        const calculatedHouseBalance = Math.max(
            pvTotal + batteryTotal + grid,
            0
        );

        const inverterPower = Number(d.inverterPower || 0);
        const calculatedHouseInverterGrid = Math.max(
            (Number.isFinite(inverterPower) ? inverterPower : 0) + grid,
            0
        );

        const houseCalculationMode = [
            'auto',
            'balance',
            'inverter-grid'
        ].includes(d.houseCalculationMode)
            ? d.houseCalculationMode
            : 'auto';

        let haus;

        if (houseCalculationMode === 'inverter-grid') {
            // Wechselrichterleistung gesamt + Netzbezug − Netzeinspeisung.
            haus = calculatedHouseInverterGrid;
        } else if (houseCalculationMode === 'balance') {
            // PV + Batterieentladung − Batterieladung
            // + Netzbezug − Netzeinspeisung.
            haus = calculatedHouseBalance;
        } else {
            // Bisheriges Verhalten: konfigurierte Hausverbrauchsvariable
            // hat Vorrang, ansonsten wird die vollständige Bilanz verwendet.
            haus =
                d.available?.housePowerConfigured &&
                Number.isFinite(Number(d.housePower))
                    ? Math.max(Number(d.housePower), 0)
                    : calculatedHouseBalance;
        }

        // Neue technische Ansicht.
        currentTechnicalLayout = ['compact', 'compact-wide', 'lite', 'lite-wide', 'full', 'full-wide'].includes(d.technicalLayout) ? d.technicalLayout : 'lite';
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

        // Hausansicht V2:
        // Die Wallbox wird separat dargestellt. Ihre positive Ladeleistung
        // wird deshalb vom Hausverbrauch abgezogen, damit sie nicht doppelt
        // als Hausverbrauch und Wallbox erscheint.
        const houseViewPower = Math.max(
            haus - (
                d.hasWallbox
                    ? Math.max(Number(wallbox.value || 0), 0)
                    : 0
            ),
            0
        );

        buildHouseView(
            d,
            grid,
            houseViewPower,
            pvs,
            batteries,
            wallbox
        );
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
            'GridPhaseL1',
            'GridPhaseL2',
            'GridPhaseL3',
            'GridFrequency',
            'GridVoltageL1',
            'GridVoltageL2',
            'GridVoltageL3',
            'GridConnectedStatus',
            'InverterPower',
            'InverterCurrentL1',
            'InverterCurrentL2',
            'InverterCurrentL3',
            'HousePower',
            'HouseEnergy',
            'AutarkyVariable',
            'SelfConsumptionVariable',
            'InverterVoltage',
            'InverterCurrent',
            'InverterFrequency',
            'InverterTemperature',
            'InverterDCTemperature',
            'OutsideTemperature',
            'SolarForecastRemaining',
        ] as $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($id > 0) {
                $ids[] = $id;
            }

        }

        $producers = json_decode($this->ReadPropertyString('Producers'), true);
        if (is_array($producers)) {
            foreach ($producers as $producer) {
                foreach ([
                    // Neue Konfiguration: genau eine Zeile pro String.
                    'VariableID',
                    'VoltageVariableID',
                    'CurrentVariableID',

                    // Alte Konfiguration bleibt zur Migration lesbar.
                    'String1PowerVariableID',
                    'String1VoltageVariableID',
                    'String1CurrentVariableID',
                    'String2PowerVariableID',
                    'String2VoltageVariableID',
                    'String2CurrentVariableID'
                ] as $key) {
                    $variableID = (int) ($producer[$key] ?? 0);
                    if ($variableID > 0) {
                        $ids[] = $variableID;
                    }
                }
            }
        }

        $inverters = json_decode($this->ReadPropertyString('Inverters'), true);
        if (is_array($inverters)) {
            foreach ($inverters as $inverter) {
                foreach ([
                    'PowerVariableID',
                    'CurrentL1VariableID',
                    'CurrentL2VariableID',
                    'CurrentL3VariableID',
                    'DailyEnergyVariableID',
                    'TotalEnergyVariableID',
                ] as $key) {
                    $variableID = (int) ($inverter[$key] ?? 0);
                    if ($variableID > 0) {
                        $ids[] = $variableID;
                    }
                }
            }
        }

        $batteries = json_decode($this->ReadPropertyString('Batteries'), true);
        if (is_array($batteries)) {
            foreach (array_slice($batteries, 0, 2) as $battery) {
                foreach ([
                    'VariableID',
                    'EnergyVariableID',
                    'ChargeEnergyVariableID',
                    'DischargeEnergyVariableID',
                    'SoCVariableID',
                    'CurrentVariableID',
                    'VoltageVariableID',
                    'TemperatureVariableID',
                    'StatusVariableID',
                    'MaxDischargeSoCVariableID'
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

                if ((bool) ($group['IsWallbox'] ?? false)) {
                    $socVariableID = $this->ResolveVariableID(
                        (int) ($group['SoCObjectID'] ?? 0)
                    );

                    if ($socVariableID > 0) {
                        $ids[] = $socVariableID;
                    }
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
        //
        // Variante A – zwei getrennte Variablen:
        //   Bezug positiv, Überschuss/Einspeisung positiv
        //   Ergebnis = Bezug - Überschuss
        //
        // Variante B – eine gemeinsame bidirektionale Variable:
        //   bisherige Modulkonvention bleibt erhalten und wird intern
        //   auf positiv = Bezug / negativ = Einspeisung normalisiert.
        $gridBase = (float) $this->ReadVar('L1');

        $gridExportPowerID =
            $this->ReadPropertyInteger('GridExportPower');

        $hasSeparateExportPower =
            $gridExportPowerID > 0 &&
            IPS_VariableExists($gridExportPowerID);

        if ($hasSeparateExportPower) {
            // Bei getrennten Variablen ist L1 ausschließlich der Bezug.
            // Die Option „Vorzeichen umkehren“ wirkt nur auf diese
            // Bezugsvariable, falls der Sensor negative Werte liefert.
            if ($this->ReadPropertyBoolean('InvertGridPower')) {
                $gridBase *= -1;
            }

            $gridImportPower = max($gridBase, 0.0);
            $gridExportPower = max(
                (float) GetValue($gridExportPowerID),
                0.0
            );

            // Wichtig: Überschuss wird vom Bezug abgezogen.
            $grid = $gridImportPower - $gridExportPower;
        } else {
            // Eine gemeinsame Netzvariable:
            // ursprüngliche Sensor-Konvention:
            // positiv = Rücklieferung, negativ = Netzbezug.
            // Interne Konvention:
            // positiv = Netzbezug, negativ = Rücklieferung.
            $grid = $gridBase * -1;

            if ($this->ReadPropertyBoolean('InvertGridPower')) {
                $grid *= -1;
            }
        }

        $pvs = [];
        $housePvs = [];
        $batteries = [];

        // PV-Strings: Eine Tabellenzeile entspricht genau einem String.
        // Die Reihenfolge im Formular wird unverändert als PV1 bis PV6
        // an die Sunsynk-Karte übergeben.
        $decodedPVs = json_decode(
            $this->ReadPropertyString('Producers'),
            true
        );

        $pvTotalPower = 0.0;

        if (is_array($decodedPVs)) {
            foreach (array_slice($decodedPVs, 0, 6) as $source) {
                $powerVariableID = (int) (
                    $source['VariableID'] ?? 0
                );

                // Abwärtskompatibilität für bisherige Anlagenzeilen:
                // Falls keine neue Leistungsvariable vorhanden ist, wird
                // der erste konfigurierte alte String übernommen.
                if (
                    $powerVariableID <= 0 ||
                    !IPS_VariableExists($powerVariableID)
                ) {
                    $legacyPowerID = (int) (
                        $source['String1PowerVariableID'] ?? 0
                    );

                    if (
                        $legacyPowerID > 0 &&
                        IPS_VariableExists($legacyPowerID)
                    ) {
                        $powerVariableID = $legacyPowerID;
                    }
                }

                if (
                    $powerVariableID <= 0 ||
                    !IPS_VariableExists($powerVariableID)
                ) {
                    continue;
                }

                if (count($pvs) >= 6) {
                    break;
                }

                $voltageVariableID = (int) (
                    $source['VoltageVariableID']
                    ?? $source['String1VoltageVariableID']
                    ?? 0
                );

                $currentVariableID = (int) (
                    $source['CurrentVariableID']
                    ?? $source['String1CurrentVariableID']
                    ?? 0
                );

                $hasVoltage =
                    $voltageVariableID > 0 &&
                    IPS_VariableExists($voltageVariableID);

                $hasCurrent =
                    $currentVariableID > 0 &&
                    IPS_VariableExists($currentVariableID);

                $power = (float) GetValue($powerVariableID);
                $name = trim((string) ($source['Name'] ?? ''));

                if ($name === '') {
                    $name = trim((string) (
                        $source['String1Name'] ?? ''
                    ));
                }

                if ($name === '') {
                    $name = 'String ' . (count($pvs) + 1);
                }

                $maxPower = max(
                    0,
                    (int) (
                        $source['MaxPower']
                        ?? $source['String1MaxPower']
                        ?? 0
                    )
                );

                $pvs[] = [
                    'name'        => $name,
                    'plantName'   => '',
                    'stringNo'    => count($pvs) + 1,
                    'value'       => $power,
                    'hasPower'    => true,
                    'energy'      => '',
                    'energyValue' => 0.0,
                    'hasEnergy'   => false,
                    'voltage'     => $hasVoltage
                        ? (float) GetValue($voltageVariableID)
                        : 0.0,
                    'hasVoltage'  => $hasVoltage,
                    'current'     => $hasCurrent
                        ? (float) GetValue($currentVariableID)
                        : 0.0,
                    'hasCurrent'  => $hasCurrent,
                    'maxPower'    => $maxPower,
                ];

                $pvTotalPower += $power;

            }
        }

        // Die Hausgrafik erhält ebenfalls die einzelnen Strings. Die
        // Energie stammt jedoch zentral aus der Wechselrichter-Liste.
        $housePvs = $pvs;



        // Batterien.
        $decodedBatteries = json_decode($this->ReadPropertyString('Batteries'), true);
        if (is_array($decodedBatteries)) {
            foreach (array_slice($decodedBatteries, 0, 2) as $source) {
                $variableID = (int) ($source['VariableID'] ?? 0);
                if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                    continue;
                }

                $chargeEnergyVariableID = (int) ($source['ChargeEnergyVariableID'] ?? 0);
                $dischargeEnergyVariableID = (int) ($source['DischargeEnergyVariableID'] ?? 0);
                $socVariableID = (int) ($source['SoCVariableID'] ?? 0);
                $currentVariableID = (int) ($source['CurrentVariableID'] ?? 0);
                $voltageVariableID = (int) ($source['VoltageVariableID'] ?? 0);
                $temperatureVariableID = (int) ($source['TemperatureVariableID'] ?? 0);
                $statusVariableID = (int) ($source['StatusVariableID'] ?? 0);
                $maxDischargeSoCVariableID = (int) ($source['MaxDischargeSoCVariableID'] ?? 0);

                $hasStatus =
                    $statusVariableID > 0 &&
                    IPS_VariableExists($statusVariableID);

                $statusText = '';
                if ($hasStatus) {
                    $formattedStatus = trim((string) GetValueFormatted($statusVariableID));
                    if ($formattedStatus !== '') {
                        $statusText = $formattedStatus;
                    } else {
                        $rawStatus = GetValue($statusVariableID);
                        $statusText = is_bool($rawStatus)
                            ? ($rawStatus ? 'Ein' : 'Aus')
                            : trim((string) $rawStatus);
                    }
                }

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
                    'hasPower'             => ($variableID > 0 && IPS_VariableExists($variableID)),
                    'hasSoc'               => ($socVariableID > 0 && IPS_VariableExists($socVariableID)),
                    'invertFlow'            => (bool) ($source['InvertFlow'] ?? false),
                    'soc'                  => ($socVariableID > 0 && IPS_VariableExists($socVariableID))
                        ? (float) GetValue($socVariableID)
                        : 0.0,
                    'hasCurrent'           => ($currentVariableID > 0 && IPS_VariableExists($currentVariableID)),
                    'current'              => ($currentVariableID > 0 && IPS_VariableExists($currentVariableID))
                        ? (float) GetValue($currentVariableID)
                        : 0.0,
                    'hasVoltage'           => ($voltageVariableID > 0 && IPS_VariableExists($voltageVariableID)),
                    'voltage'              => ($voltageVariableID > 0 && IPS_VariableExists($voltageVariableID))
                        ? (float) GetValue($voltageVariableID)
                        : 0.0,
                    'hasTemperature'       => (
                        $temperatureVariableID > 0
                        && IPS_VariableExists($temperatureVariableID)
                    ),
                    'temperature'          => (
                        $temperatureVariableID > 0
                        && IPS_VariableExists($temperatureVariableID)
                    )
                        ? (float) GetValue($temperatureVariableID)
                        : 0.0,
                    'hasStatus'            => $hasStatus,
                    'statusText'           => $statusText,
                    'maxDischargeSoc'      => max(
                        0,
                        min(
                            100,
                            (int) round(
                                ($maxDischargeSoCVariableID > 0 && IPS_VariableExists($maxDischargeSoCVariableID))
                                    ? (float) GetValue($maxDischargeSoCVariableID)
                                    : 0.0
                            )
                        )
                    ),
                    'capacityKWh'          => max(
                        0.0,
                        (float) ($source['CapacityKWh'] ?? 0.0)
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

                $isWallbox = (bool) ($group['IsWallbox'] ?? false);
                $socObjectID = (int) ($group['SoCObjectID'] ?? 0);
                $socVariableID = $isWallbox
                    ? $this->ResolveVariableID($socObjectID)
                    : 0;

                $hasSoc =
                    $socVariableID > 0 &&
                    IPS_VariableExists($socVariableID);

                $socText = '';
                if ($hasSoc) {
                    // GetValueFormatted unterstützt Boolean, Integer, Float
                    // und String. Bei numerischen Variablen werden außerdem
                    // Präfix, Suffix und Zuordnungstexte des Variablenprofils
                    // übernommen, z. B. "69 %", "Voll" oder "Lädt".
                    $socText = trim((string) GetValueFormatted($socVariableID));

                    // Sicherheitsrückfall, falls ein Profil keinen formatierten
                    // Text liefert.
                    if ($socText === '') {
                        $socValue = GetValue($socVariableID);
                        $socText = is_bool($socValue)
                            ? ($socValue ? 'true' : 'false')
                            : trim((string) $socValue);
                    }
                }

                $groups[] = [
                    'name'  => (string) ($group['Name'] ?? ''),
                    'icon'  => (string) ($group['Icon'] ?? 'plug'),
                    'value' => $value,
                    'hasPower' => ($variableID > 0 && IPS_VariableExists($variableID)),
                    'daily' => $daily,
                    'dailyValue' => $dailyValue,
                    'hasDaily' => $hasDaily,
                    'displayThreshold' => max(
                        0.0,
                        (float) ($group['DisplayThreshold'] ?? 0)
                    ),
                    'isAux' => (bool) ($group['IsAux'] ?? false),
                    'isWallbox' => $isWallbox,
                    'socText' => $socText,
                    'hasSoc' => $hasSoc,
                ];
            }
        }

        // Die erste als Wallbox markierte Verbraucherzeile liefert zusätzlich
        // die Wallboxdaten für die Hausgrafik. In der technischen Ansicht bleibt
        // sie ein ganz normaler Verbraucher und wird nach Leistung sortiert.
        $wallbox = [
            'name'        => 'Wallbox',
            'value'       => 0.0,
            'energy'      => '',
            'energyValue' => 0.0,
            'hasEnergy'   => false,
            'socText'     => '',
            'hasSoc'      => false,
        ];

        $hasWallbox = false;

        foreach ($groups as $group) {
            if (!($group['isWallbox'] ?? false)) {
                continue;
            }

            $wallbox = [
                'name'        => trim((string) ($group['name'] ?? '')) !== ''
                    ? (string) $group['name']
                    : 'Wallbox',
                'value'       => (float) ($group['value'] ?? 0.0),
                'energy'      => (string) ($group['daily'] ?? ''),
                'energyValue' => (float) ($group['dailyValue'] ?? 0.0),
                'hasEnergy'   => (bool) ($group['hasDaily'] ?? false),
                'socText'     => (string) ($group['socText'] ?? ''),
                'hasSoc'      => (bool) ($group['hasSoc'] ?? false),
            ];

            $hasWallbox = (bool) ($group['hasPower'] ?? false);
            break;
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

        // Die PV-Tagesenergie wird weiter unten nach dem Auslesen aller
        // Wechselrichter aus deren aufsummierter Tagesenergie übernommen.
        $pvEnergyTotal = 0.0;
        $hasPvEnergy = false;

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

        $houseEnergyAvailable = false;
        $houseEnergy = 0.0;

        // Wechselrichter: Alle Listeneinträge werden zu Gesamtwerten addiert.
        // Die beiden Temperaturwerte bleiben getrennt: WR1 wird auf dem
        // bisherigen AC-Temperaturfeld, WR2 auf dem DC-Temperaturfeld der
        // Sunsynk-Karte dargestellt. So geht keine Gerätetemperatur verloren.
        // Sind noch keine Listeneinträge konfiguriert, bleiben die bisherigen
        // Einzel-Eigenschaften als abwärtskompatibler Fallback aktiv.
        $inverterPower = 0.0;
        $inverterCurrentL1 = 0.0;
        $inverterCurrentL2 = 0.0;
        $inverterCurrentL3 = 0.0;
        $inverterDailyEnergy = 0.0;
        $inverterTotalEnergy = 0.0;
        $inverterPowerAvailable = false;
        $inverterCurrentL1Available = false;
        $inverterCurrentL2Available = false;
        $inverterCurrentL3Available = false;
        $inverterDailyEnergyAvailable = false;
        $inverterTotalEnergyAvailable = false;
        $hasConfiguredInverterList = false;
        $inverterDetails = [];

        $decodedInverters = json_decode(
            $this->ReadPropertyString('Inverters'),
            true
        );

        if (is_array($decodedInverters)) {
            foreach ($decodedInverters as $index => $source) {
                $ids = [
                    'power' => (int) ($source['PowerVariableID'] ?? 0),
                    'l1'    => (int) ($source['CurrentL1VariableID'] ?? 0),
                    'l2'    => (int) ($source['CurrentL2VariableID'] ?? 0),
                    'l3'    => (int) ($source['CurrentL3VariableID'] ?? 0),
                    'daily' => (int) ($source['DailyEnergyVariableID'] ?? 0),
                    'total' => (int) ($source['TotalEnergyVariableID'] ?? 0),
                ];

                if (max($ids) > 0) {
                    $hasConfiguredInverterList = true;
                }

                $powerAvailable = $ids['power'] > 0 && IPS_VariableExists($ids['power']);
                $l1Available = $ids['l1'] > 0 && IPS_VariableExists($ids['l1']);
                $l2Available = $ids['l2'] > 0 && IPS_VariableExists($ids['l2']);
                $l3Available = $ids['l3'] > 0 && IPS_VariableExists($ids['l3']);
                $dailyAvailable = $ids['daily'] > 0 && IPS_VariableExists($ids['daily']);
                $totalAvailable = $ids['total'] > 0 && IPS_VariableExists($ids['total']);

                $power = $powerAvailable ? (float) GetValue($ids['power']) : 0.0;
                $currentL1 = $l1Available ? (float) GetValue($ids['l1']) : 0.0;
                $currentL2 = $l2Available ? (float) GetValue($ids['l2']) : 0.0;
                $currentL3 = $l3Available ? (float) GetValue($ids['l3']) : 0.0;
                $dailyEnergy = $dailyAvailable ? (float) GetValue($ids['daily']) : 0.0;
                $totalEnergy = $totalAvailable ? (float) GetValue($ids['total']) : 0.0;

                $inverterPower += $power;
                $inverterCurrentL1 += $currentL1;
                $inverterCurrentL2 += $currentL2;
                $inverterCurrentL3 += $currentL3;
                $inverterDailyEnergy += $dailyEnergy;
                $inverterTotalEnergy += $totalEnergy;

                $inverterPowerAvailable = $inverterPowerAvailable || $powerAvailable;
                $inverterCurrentL1Available = $inverterCurrentL1Available || $l1Available;
                $inverterCurrentL2Available = $inverterCurrentL2Available || $l2Available;
                $inverterCurrentL3Available = $inverterCurrentL3Available || $l3Available;
                $inverterDailyEnergyAvailable = $inverterDailyEnergyAvailable || $dailyAvailable;
                $inverterTotalEnergyAvailable = $inverterTotalEnergyAvailable || $totalAvailable;

                $inverterDetails[] = [
                    'name' => trim((string) ($source['Name'] ?? '')) ?: ('WR' . ($index + 1)),
                    'power' => $power,
                    'currentL1' => $currentL1,
                    'currentL2' => $currentL2,
                    'currentL3' => $currentL3,
                    'dailyEnergy' => $dailyEnergy,
                    'totalEnergy' => $totalEnergy,
                    'hasPower' => $powerAvailable,
                    'hasCurrentL1' => $l1Available,
                    'hasCurrentL2' => $l2Available,
                    'hasCurrentL3' => $l3Available,
                    'hasDailyEnergy' => $dailyAvailable,
                    'hasTotalEnergy' => $totalAvailable,
                ];
            }
        }

        if (!$hasConfiguredInverterList) {
            $legacyPowerID = $this->ReadPropertyInteger('InverterPower');
            $legacyL1ID = $this->ReadPropertyInteger('InverterCurrentL1');
            $legacyL2ID = $this->ReadPropertyInteger('InverterCurrentL2');
            $legacyL3ID = $this->ReadPropertyInteger('InverterCurrentL3');

            $inverterPowerAvailable = $legacyPowerID > 0 && IPS_VariableExists($legacyPowerID);
            $inverterCurrentL1Available = $legacyL1ID > 0 && IPS_VariableExists($legacyL1ID);
            $inverterCurrentL2Available = $legacyL2ID > 0 && IPS_VariableExists($legacyL2ID);
            $inverterCurrentL3Available = $legacyL3ID > 0 && IPS_VariableExists($legacyL3ID);

            $inverterPower = $inverterPowerAvailable ? (float) GetValue($legacyPowerID) : 0.0;
            $inverterCurrentL1 = $inverterCurrentL1Available ? (float) GetValue($legacyL1ID) : 0.0;
            $inverterCurrentL2 = $inverterCurrentL2Available ? (float) GetValue($legacyL2ID) : 0.0;
            $inverterCurrentL3 = $inverterCurrentL3Available ? (float) GetValue($legacyL3ID) : 0.0;

            $inverterDetails = [[
                'name' => 'WR1',
                'power' => $inverterPower,
                'currentL1' => $inverterCurrentL1,
                'currentL2' => $inverterCurrentL2,
                'currentL3' => $inverterCurrentL3,
                'dailyEnergy' => 0.0,
                'totalEnergy' => 0.0,
                'hasPower' => $inverterPowerAvailable,
                'hasCurrentL1' => $inverterCurrentL1Available,
                'hasCurrentL2' => $inverterCurrentL2Available,
                'hasCurrentL3' => $inverterCurrentL3Available,
                'hasDailyEnergy' => false,
                'hasTotalEnergy' => false,
            ]];
        }

        // Für sämtliche PV-Energieanzeigen gilt zentral die Summe der
        // Tagesenergien aus der Wechselrichter-Liste.
        $pvEnergyTotal = $inverterDailyEnergy;
        $hasPvEnergy = $inverterDailyEnergyAvailable;

        $houseEnergy = 0.0;
        $houseEnergyAvailable = false;
        $houseCalculationMode = $this->ReadPropertyString('HouseCalculationMode');

        $houseEnergyID = $this->ReadPropertyInteger('HouseEnergy');
        $hasConfiguredHouseEnergy =
            $houseEnergyID > 0 &&
            IPS_VariableExists($houseEnergyID);

        $balanceEnergyAvailable =
            $hasPvEnergy &&
            $hasGridImportEnergy &&
            $hasGridExportEnergy &&
            $hasBatteryEnergy;

        $inverterGridEnergyAvailable =
            $inverterDailyEnergyAvailable &&
            $hasGridImportEnergy &&
            $hasGridExportEnergy;

        if ($houseCalculationMode === 'auto' && $hasConfiguredHouseEnergy) {
            $houseEnergy = max(0.0, (float) GetValue($houseEnergyID));
            $houseEnergyAvailable = true;
        } elseif ($houseCalculationMode === 'inverter-grid') {
            if ($inverterGridEnergyAvailable) {
                $houseEnergy = max(
                    0.0,
                    $inverterDailyEnergy +
                    (float) GetValue($gridImportEnergyID) -
                    (float) GetValue($gridExportEnergyID)
                );
                $houseEnergyAvailable = true;
            }
        } elseif ($balanceEnergyAvailable) {
            // Gilt für "balance" sowie als Rückfall von "auto".
            $houseEnergy = max(
                0.0,
                $pvEnergyTotal +
                (float) GetValue($gridImportEnergyID) -
                (float) GetValue($gridExportEnergyID) +
                $batteryDischargeEnergyTotal -
                $batteryChargeEnergyTotal
            );
            $houseEnergyAvailable = true;
        }

        // Die beiden Temperaturfelder gehören zur zentralen
        // Wechselrichterdarstellung der Sunsynk-Karte und werden nicht
        // pro Eintrag der Wechselrichterliste summiert oder zugeordnet.
        $inverterACTemperatureID = $this->ReadPropertyInteger('InverterTemperature');
        $inverterDCTemperatureID = $this->ReadPropertyInteger('InverterDCTemperature');
        $inverterTemperatureAvailable = $inverterACTemperatureID > 0
            && IPS_VariableExists($inverterACTemperatureID);
        $inverter2TemperatureAvailable = $inverterDCTemperatureID > 0
            && IPS_VariableExists($inverterDCTemperatureID);
        $inverterTemperature = $inverterTemperatureAvailable
            ? (float) GetValue($inverterACTemperatureID)
            : 0.0;
        $inverter2Temperature = $inverter2TemperatureAvailable
            ? (float) GetValue($inverterDCTemperatureID)
            : 0.0;

        $gridConnectedRaw = $this->ReadPropertyInteger('GridConnectedStatus') > 0
            ? $this->ReadVar('GridConnectedStatus')
            : 1.0;
        $gridConnectedStatus = ((float) $gridConnectedRaw) != 0.0 ? 'on-grid' : 'off-grid';

        return [
            'displayMode'      => $this->ReadPropertyString('DisplayMode'),
            'technicalLayout'  => $this->ReadPropertyString('TechnicalLayout'),
            'pvs'              => $pvs,
            'housePvs'         => $housePvs,
            'batteries'        => $batteries,
            'grid'             => $grid,
            'gridPhaseL1'     => $this->ReadVar('GridPhaseL1'),
            'gridPhaseL2'     => $this->ReadVar('GridPhaseL2'),
            'gridPhaseL3'     => $this->ReadVar('GridPhaseL3'),
            'gridPhaseL1Available' => ($this->ReadPropertyInteger('GridPhaseL1') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridPhaseL1'))),
            'gridFrequency'    => $this->ReadVar('GridFrequency'),
            'gridVoltageL1'    => $this->ReadVar('GridVoltageL1'),
            'gridVoltageL2'    => $this->ReadVar('GridVoltageL2'),
            'gridVoltageL3'    => $this->ReadVar('GridVoltageL3'),
            'gridConnectedStatus' => $gridConnectedStatus,
            'housePower'       => $this->ReadVar('HousePower'),
            'houseCalculationMode' => $this->ReadPropertyString(
                'HouseCalculationMode'
            ),
            'autarkyCalculationMode' => $this->ReadPropertyString(
                'AutarkyCalculationMode'
            ),
            'autarkyVariableValue' => $this->ReadVar('AutarkyVariable'),
            'autarkyVariableAvailable' => (
                $this->ReadPropertyInteger('AutarkyVariable') > 0
                && IPS_VariableExists($this->ReadPropertyInteger('AutarkyVariable'))
            ),
            'selfConsumptionVariableValue' => $this->ReadVar(
                'SelfConsumptionVariable'
            ),
            'selfConsumptionVariableAvailable' => (
                $this->ReadPropertyInteger('SelfConsumptionVariable') > 0
                && IPS_VariableExists(
                    $this->ReadPropertyInteger('SelfConsumptionVariable')
                )
            ),
            'inverterPower'    => $inverterPower,
            'inverterCurrentL1' => $inverterCurrentL1,
            'inverterCurrentL2' => $inverterCurrentL2,
            'inverterCurrentL3' => $inverterCurrentL3,
            'inverterPowerAvailable' => $inverterPowerAvailable,
            'inverters' => $inverterDetails,
            'inverterDailyEnergy' => $inverterDailyEnergy,
            'inverterTotalEnergy' => $inverterTotalEnergy,
            'inverter2Temperature' => $inverter2Temperature,
            'outsideTemperature' => $this->ReadVar('OutsideTemperature'),
            'solarForecastRemaining' => $this->ReadVar(
                'SolarForecastRemaining'
            ),
            'inverterVoltage'  => $this->ReadVar('InverterVoltage'),
            'inverterCurrent'  => $this->ReadVar('InverterCurrent'),
            'inverterFrequency'=> $this->ReadVar('InverterFrequency'),
            'inverterTemperature' => $inverterTemperature,
            'available' => [
                'gridPower' => ($this->ReadPropertyInteger('L1') > 0 && IPS_VariableExists($this->ReadPropertyInteger('L1'))),
                'gridPhaseL2' => ($this->ReadPropertyInteger('GridPhaseL2') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridPhaseL2'))),
                'gridPhaseL3' => ($this->ReadPropertyInteger('GridPhaseL3') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridPhaseL3'))),
                'gridFrequency' => ($this->ReadPropertyInteger('GridFrequency') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridFrequency'))),
                'gridVoltageL1' => ($this->ReadPropertyInteger('GridVoltageL1') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridVoltageL1'))),
                'gridVoltageL2' => ($this->ReadPropertyInteger('GridVoltageL2') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridVoltageL2'))),
                'gridVoltageL3' => ($this->ReadPropertyInteger('GridVoltageL3') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridVoltageL3'))),
                'gridStatus' => ($this->ReadPropertyInteger('GridConnectedStatus') > 0 && IPS_VariableExists($this->ReadPropertyInteger('GridConnectedStatus'))),
                'inverterPower' => $inverterPowerAvailable,
                'inverterCurrentL1' => $inverterCurrentL1Available,
                'inverterCurrentL2' => $inverterCurrentL2Available,
                'inverterCurrentL3' => $inverterCurrentL3Available,
                'housePowerConfigured' => ($this->ReadPropertyInteger('HousePower') > 0 && IPS_VariableExists($this->ReadPropertyInteger('HousePower'))),
                'houseEnergyConfigured' => ($this->ReadPropertyInteger('HouseEnergy') > 0 && IPS_VariableExists($this->ReadPropertyInteger('HouseEnergy'))),
                'autarkyVariableConfigured' => (
                    $this->ReadPropertyInteger('AutarkyVariable') > 0
                    && IPS_VariableExists(
                        $this->ReadPropertyInteger('AutarkyVariable')
                    )
                ),
                'selfConsumptionVariableConfigured' => (
                    $this->ReadPropertyInteger('SelfConsumptionVariable') > 0
                    && IPS_VariableExists(
                        $this->ReadPropertyInteger('SelfConsumptionVariable')
                    )
                ),
                'outsideTemperature' => (
                    $this->ReadPropertyInteger('OutsideTemperature') > 0
                    && IPS_VariableExists($this->ReadPropertyInteger('OutsideTemperature'))
                ),
                'inverterTemperature' => $inverterTemperatureAvailable,
                'inverter2Temperature' => $inverter2TemperatureAvailable,
                'inverterDailyEnergy' => $inverterDailyEnergyAvailable,
                'inverterTotalEnergy' => $inverterTotalEnergyAvailable,
                'solarForecastRemaining' => (
                    $this->ReadPropertyInteger('SolarForecastRemaining') > 0
                    && IPS_VariableExists(
                        $this->ReadPropertyInteger('SolarForecastRemaining')
                    )
                ),
            ],
            'gridImportEnergy' => $this->ReadVarFormatted('GridImportEnergy'),
            'gridExportEnergy' => $this->ReadVarFormatted('GridExportEnergy'),
            'gridImportEnergyValue' => $hasGridImportEnergy ? (float) GetValue($gridImportEnergyID) : 0.0,
            'gridExportEnergyValue' => $hasGridExportEnergy ? (float) GetValue($gridExportEnergyID) : 0.0,
            'gridImportEnergyValueAvailable' => $hasGridImportEnergy,
            'gridExportEnergyValueAvailable' => $hasGridExportEnergy,
            'houseEnergy'       => $houseEnergy,
            'houseEnergyAvailable' => $houseEnergyAvailable,
            'wallbox'          => $wallbox,
            'hasWallbox'       => $hasWallbox,
            'groups'           => $groups,
            'flowSpeedPercent' => $this->ReadPropertyInteger('FlowSpeedPercent'),
            'solarMaxPower'    => max(
                1,
                array_sum(
                    array_map(
                        static fn(array $pv): int => max(0, (int) ($pv['maxPower'] ?? 0)),
                        $pvs
                    )
                )
            ),
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
                'inverter'  => $this->ColorToHex($this->ReadPropertyInteger('HouseColorInverter')),
                'solar'     => $this->ColorToHex($this->ReadPropertyInteger('ColorSolar')),
                'import'    => $this->ColorToHex($this->ReadPropertyInteger('ColorGridImport')),
                'export'    => $this->ColorToHex($this->ReadPropertyInteger('ColorGridExport')),
                'charge'    => $this->ColorToHex($this->ReadPropertyInteger('ColorBatteryCharge')),
                'discharge' => $this->ColorToHex($this->ReadPropertyInteger('ColorBatteryDischarge')),
                'room'      => $this->ColorToHex($this->ReadPropertyInteger('ColorConsumers')),
            ],
        ];
    }
}