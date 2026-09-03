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

        // Interne Berechnungsart für die aktuelle Hausleistung (W),
        // falls keine gültige Hausleistungsvariable verknüpft ist:
        // balance: PV + Batterie + Netzsaldo
        // inverter-grid: Wechselrichterleistung + Netzsaldo
        //
        // Eine gültige HousePower-Variable hat immer Vorrang.
        $this->RegisterPropertyString('HouseCalculationMode', 'balance');
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

        // Optional erzeugte Modulvariablen.
        $this->RegisterPropertyBoolean('CreateVariableHousePower', false);
        $this->RegisterPropertyBoolean('CreateVariableHouseEnergy', false);
        $this->RegisterPropertyBoolean('CreateVariableAutarky', false);
        $this->RegisterPropertyBoolean('CreateVariableSelfConsumption', false);
        $this->RegisterPropertyBoolean('CreateVariablePvPower', false);
        $this->RegisterPropertyBoolean('CreateVariablePvEnergy', false);
        $this->RegisterPropertyBoolean('CreateVariableInverterPower', false);
        $this->RegisterPropertyBoolean('CreateVariableGridImportPower', false);
        $this->RegisterPropertyBoolean('CreateVariableGridExportPower', false);
        $this->RegisterPropertyBoolean('CreateVariableBatteryPower', false);
        $this->RegisterPropertyBoolean('CreateVariableBatteryChargeEnergy', false);
        $this->RegisterPropertyBoolean('CreateVariableBatteryDischargeEnergy', false);
        $this->RegisterPropertyBoolean('CreateVariableBatteryRuntime', false);
        $this->RegisterPropertyBoolean('CreateVariableWallboxPower', false);
        $this->RegisterPropertyBoolean('CreateVariableWallboxEnergy', false);

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

            $this->ConfigureCalculatedVariables();

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
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'SOC',
                                    'name'    => 'SoCVariableID',
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Strom (A)',
                                    'name'    => 'CurrentVariableID',
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Spannung (V)',
                                    'name'    => 'VoltageVariableID',
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Temperatur (°C)',
                                    'name'    => 'TemperatureVariableID',
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Status (optional)',
                                    'name'    => 'StatusVariableID',
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Max. Entladezustand',
                                    'name'    => 'MaxDischargeSoCVariableID',
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Kapazität (kWh)',
                                    'name'    => 'CapacityKWh',
                                    'width'   => '120px',
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
                                    'width'   => '120px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Ladeenergie (kWh)',
                                    'name'    => 'ChargeEnergyVariableID',
                                    'width'   => '120px',
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
                            'caption' => 'Netzbezug heute (kWh)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'GridExportEnergy',
                            'caption' => 'Netzeinspeisung heute (kWh)',
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
                            'caption' => 'Hausleistung und Hausenergie',
                        ],
                        [
                            'type'    => 'Select',
                            'name'    => 'HouseCalculationMode',
                            'caption' => 'Interne Berechnung der Hausleistung (nur ohne verknüpfte Variable)',
                            'options' => [
                                [
                                    'caption' => 'PV-Leistung + Batterie + Netzsaldo',
                                    'value'   => 'balance',
                                ],
                                [
                                    'caption' => 'Wechselrichterleistung gesamt + Netzsaldo',
                                    'value'   => 'inverter-grid',
                                ],
                            ],
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'HousePower',
                            'caption' => 'Hausleistung (W, optional – sonst interne Berechnung)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'HouseEnergy',
                            'caption' => 'Hausverbrauch heute (kWh, optional – sonst interne Berechnung)',
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
                                    'caption' => 'Leistungs-Variable (W)',
                                    'name'    => 'VariableID',
                                    'width'   => '230px',
                                    'add'     => 0,
                                    'edit'    => ['type' => 'SelectVariable'],
                                ],
                                [
                                    'caption' => 'Tagesverbrauch (kWh, optional)',
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
                    'caption' => 'Berechnete Variablen',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Aktivierte Werte werden als Variablen unter der Modulinstanz angelegt und laufend aktualisiert. Beim Deaktivieren wird die betreffende Variable wieder entfernt.',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableHousePower',
                            'caption' => 'Hausleistung (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableHouseEnergy',
                            'caption' => 'Hausenergie heute (kWh)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableAutarky',
                            'caption' => 'Autarkie (%)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableSelfConsumption',
                            'caption' => 'Eigenverbrauch (%)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariablePvPower',
                            'caption' => 'PV-Gesamtleistung (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariablePvEnergy',
                            'caption' => 'PV-Tagesenergie (kWh)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableInverterPower',
                            'caption' => 'Inverterleistung gesamt (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableGridImportPower',
                            'caption' => 'Netzbezug (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableGridExportPower',
                            'caption' => 'Netzeinspeisung (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableBatteryPower',
                            'caption' => 'Batterieleistung gesamt (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableBatteryChargeEnergy',
                            'caption' => 'Batterie-Ladeenergie heute (kWh)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableBatteryDischargeEnergy',
                            'caption' => 'Batterie-Entladeenergie heute (kWh)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableBatteryRuntime',
                            'caption' => 'Batterie-Laufzeit (String)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableWallboxPower',
                            'caption' => 'Wallbox-Leistung (W)',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'CreateVariableWallboxEnergy',
                            'caption' => 'Wallbox-Energie heute (kWh)',
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

                // Nur das geöffnete Konfigurationsformular aktualisieren.
                // Der Benutzer kann die importierten Werte prüfen und anschließend
                // selbst über "Änderungen übernehmen" in die Instanz schreiben.
                $this->UpdateFormField($property, 'value', $color);
                $imported++;
            }

            if ($imported === 0) {
                return 'Im JSON wurden keine bekannten Hausfarben gefunden.';
            }

            // Das Importfeld ist nur eine temporäre Eingabe. Auch diese Änderung
            // bleibt zunächst im geöffneten Formular und wird nicht direkt gespeichert.
            $this->UpdateFormField('HouseColorJson', 'value', '');

            return sprintf(
                '%d Hausfarben wurden in die Konfiguration übernommen. Bitte prüfen und mit "Änderungen übernehmen" speichern.',
                $imported
            );
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
        // Originalfarben der eingebetteten Haus-SVG in das geöffnete
        // Konfigurationsformular eintragen. Gespeichert werden sie erst, wenn
        // der Benutzer "Änderungen übernehmen" auswählt.
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
            $this->UpdateFormField($property, 'value', $value);
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

            $gridPayload = json_encode(
                $this->GetVisualizationGridPayload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            // Sofortiger Browserzustand wie bei der Wärmepumpe.
            // Die Widget-ID wird anschließend nur ergänzend ermittelt.
            return $this->GetVisualizationHtml('flow')
                . '<script>window.__EF_SERVER_GRID__=' . $gridPayload
                . ';handleMessage(' . $payload . ');</script>';
        } catch (Throwable $e) {
            return '<div style="padding:1em">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    private function GetVisualizationGridPayload(): ?array
    {
        try {
            $visuID = isset($_GET['visuID']) ? (int) $_GET['visuID'] : 0;
            if ($visuID <= 0 || !function_exists('VISU_GetSnapshot')) {
                return null;
            }

            $snapshotRaw = VISU_GetSnapshot($visuID);
            $snapshot = is_string($snapshotRaw)
                ? json_decode($snapshotRaw, true, 512, JSON_THROW_ON_ERROR)
                : $snapshotRaw;

            if (!is_array($snapshot)) {
                return null;
            }

            $objectKey = 'ID' . $visuID;
            $gridRaw = $snapshot['objects'][$objectKey]['data']['attributes']['GridConfiguration'] ?? null;
            if ($gridRaw === null) {
                return null;
            }

            $grid = is_string($gridRaw)
                ? json_decode($gridRaw, true, 512, JSON_THROW_ON_ERROR)
                : $gridRaw;

            if (!is_array($grid)) {
                return null;
            }

            // Die Grid-Widget-IDs sind echte Symcon-Objekt-/Link-IDs. Für
            // Links lösen wir das Zielobjekt auf. Damit kann JavaScript später
            // /visu/36446/ direkt gegen die zugehörigen Link-Widgets filtern.
            $widgetTargets = [];
            foreach ($this->CollectVisualizationWidgetIDs($grid) as $widgetID) {
                $targetID = $this->ResolveVisualizationWidgetTarget($widgetID);
                if ($targetID > 0) {
                    $widgetTargets[(string) $widgetID] = $targetID;
                }
            }

            return [
                'visuID'  => $visuID,
                'grid'    => $grid,
                'targets' => $widgetTargets
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Sammelt alle Widget-IDs aus sämtlichen individualPositions-Blöcken der
     * GridConfiguration, unabhängig von Profil (~Desktop/~Phone) und Ausrichtung.
     */
    private function CollectVisualizationWidgetIDs(array $node): array
    {
        $ids = [];
        $walk = function ($value) use (&$walk, &$ids): void {
            if (!is_array($value)) {
                return;
            }
            foreach ($value as $key => $child) {
                if ($key === 'individualPositions' && is_array($child)) {
                    foreach ($child as $containerWidgets) {
                        if (!is_array($containerWidgets)) {
                            continue;
                        }
                        foreach (array_keys($containerWidgets) as $widgetID) {
                            if (is_numeric($widgetID)) {
                                $ids[(int) $widgetID] = true;
                            }
                        }
                    }
                }
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($node);
        return array_keys($ids);
    }

    /**
     * Liefert für eine Kachel-ID das eigentliche Zielobjekt. Ist die Kachel ein
     * Link, wird dessen TargetID verwendet; ansonsten ist die Widget-ID selbst
     * das Zielobjekt.
     */
    private function ResolveVisualizationWidgetTarget(int $widgetID): int
    {
        if ($widgetID <= 0 || !IPS_ObjectExists($widgetID)) {
            return 0;
        }
        try {
            $object = IPS_GetObject($widgetID);
            // ObjectType 6 = Link
            if ((int) ($object['ObjectType'] ?? -1) === 6 && function_exists('IPS_GetLink')) {
                $link = IPS_GetLink($widgetID);
                $targetID = (int) ($link['TargetID'] ?? 0);
                return $targetID > 0 ? $targetID : $widgetID;
            }
        } catch (Throwable $e) {
            return $widgetID;
        }
        return $widgetID;
    }

    private function GetVisualizationHtml(string $displayMode): string
    {
        $showHouse = $displayMode === 'house';
        $flowDisplay = $showHouse ? 'none' : 'block';
        $houseDisplay = $showHouse ? 'block' : 'none';

        // Die Vendor-Dateien bleiben vollständig im Modulverzeichnis. Sie werden
        // als data:-Modul-URLs direkt in das HTML injiziert; dadurch ist weder ein
        // öffentliches Laufzeitverzeichnis noch ein zusätzlicher WebHook erforderlich.
        $litModuleUrl = $this->GetVisualizationModuleDataUrl('lit-core.min.js');
        $powerFlowModuleUrl = $this->GetVisualizationModuleDataUrl(
            'power-flow-card.js',
            ['./lit-core.min.js' => $litModuleUrl]
        );
        $sunsynkModuleUrl = $this->GetVisualizationModuleDataUrl(
            'sunsynk-power-flow-card.js',
            ['./lit-core.min.js' => $litModuleUrl]
        );

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
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        column-gap: 8px;
        padding: 6px 4px 0;
        min-height: 32px;
    }

    .display-mode-slot {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    #display-mode-left {
        justify-content: flex-end;
    }

    #display-mode-right {
        justify-content: flex-start;
    }

    #display-mode-button {
        justify-self: center;
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
<script type="module" src="__POWER_FLOW_MODULE_URL__"></script>

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
        <div id="display-mode-left" class="display-mode-slot">
            <button id="technical-wide-button" type="button" title="Wide-Ansicht umschalten" aria-label="Wide-Ansicht umschalten">⬌</button>
        </div>
        <button id="display-mode-button" type="button" title="Ansicht wechseln" aria-label="Ansicht wechseln">⇄</button>
        <div id="display-mode-right" class="display-mode-slot">
            <button id="technical-layout-button" type="button" title="Technikansicht wechseln" aria-label="Technikansicht wechseln">L</button>
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
        // Immer nur EIN Gesamtwert (Saldo) anzeigen.
        // positiv = Netzbezug -> rot
        // negativ = Einspeisung -> grün
        const gridInfo = document.getElementById('pfc-info-grid');
        const gridImportEl = document.getElementById('pfc-grid-import');
        const gridExportEl = document.getElementById('pfc-grid-export');
        const gridSub = document.getElementById('pfc-grid-sub');

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

        // Darunter Bezug / Einspeisung mit den vorhandenen Energiewerten.
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

        requestAnimationFrame(alignHomeInfoToSolarBottom);
    }

    function updatePowerFlowCard(d, grid, haus, pvs, batteries, wallbox) {
        // Nur für die Hausansicht:
        // Die Wallbox wird separat dargestellt. Deshalb ihre Tagesenergie
        // auch aus der angezeigten Hausenergie herausrechnen, analog zur
        // bereits separat behandelten Wallbox-Leistung.
        //
        // d.houseEnergy selbst wird NICHT verändert. Damit bleiben sämtliche
        // berechneten IP-Symcon-Variablen und die technische Ansicht unberührt.
        const visualHouseEnergy = Math.max(
            Number(d.houseEnergy || 0) -
            (
                d.hasWallbox && wallbox?.hasEnergy
                    ? Math.max(Number(wallbox.energyValue || 0), 0)
                    : 0
            ),
            0
        );

        const houseViewData = {
            ...d,
            houseEnergy: visualHouseEnergy
        };

        if (!pfcCard) {
            updatePfcInfoCards(
                houseViewData,
                grid,
                haus,
                pvs,
                batteries,
                wallbox
            );
            pfcPendingData = [
                houseViewData,
                grid,
                haus,
                pvs,
                batteries,
                wallbox
            ];
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
        updatePfcInfoCards(
            houseViewData,
            grid,
            haus,
            pvs,
            batteries,
            wallbox
        );

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
            const moduleUrl = '__SUNSYNK_MODULE_URL__';
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
        const compact = style === 'compact';

        // Der originale Sunsynk-Compact-Cardstyle besitzt kein separates
        // Batteriedatenfenster. Damit Compact trotzdem dieselben
        // Batteriedetails wie Lite/Large und Full zeigt, wird nur der
        // interne Cardstyle auf "lite" gesetzt. Die übrige Modullogik
        // bleibt weiterhin Compact.
        const cardStyle = compact ? 'lite' : style;

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
        // AUX wird ausschließlich in Full / Full Wide separat dargestellt.
        // In Compact / Lite (inkl. Wide) werden als AUX markierte Verbraucher
        // wie normale Verbraucher behandelt, da diese Ansichten keinen
        // eigenen AUX-Bereich besitzen.
        const auxGroups = full
            ? configuredConsumers
                .filter(group => group.isAux === true)
                .slice(0, 2)
            : [];

        // Die Mindestleistung gilt ausschließlich für normale Verbraucher.
        // Außerhalb von Full gehören damit auch als AUX markierte Einträge
        // automatisch zu den normalen Verbrauchern.
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

        // Die Original-Sunsynk-Full-Ansicht behandelt exakt drei
        // zusätzliche Verbraucher inkonsistent. Für diesen einen Fall
        // verwenden wir intern die 4er-Geometrie und entfernen den
        // unbenutzten vierten Slot nach dem Rendern vollständig.
        window.__symconFullThreeConsumers =
            full &&
            auxGroups.length === 0 &&
            activeGroups.length === 3;

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
            if (full) {
                // Full wird separat behandelt. Für Compact/Lite darf kein
                // ungültiger Entity-Wert "none" übergeben werden, da dadurch
                // das komplette Batteriefenster verschwinden kann.
                entities['battery_current_191'] =
                    activeBatteries[0].hasCurrent
                        ? 'sensor.symcon_battery_current'
                        : 'none';
            } else {
                // Exakt dasselbe Muster wie bei der Batteriespannung:
                // Nur bei tatsächlich konfigurierter Variable hinzufügen.
                addEntity(
                    'battery_current_191',
                    'sensor.symcon_battery_current',
                    activeBatteries[0].hasCurrent
                );
            }
            addEntity('battery_voltage_183', 'sensor.symcon_battery_voltage', activeBatteries[0].hasVoltage);
            addEntity('battery_temp_182', 'sensor.symcon_battery_temperature', activeBatteries[0].hasTemperature);
            addEntity(
                'battery_status',
                'sensor.symcon_battery_status',
                activeBatteries[0].hasStatus
            );
            addEntity('day_battery_charge_70', 'sensor.symcon_battery_charge_energy', activeBatteries[0].hasChargeEnergy);
            addEntity('day_battery_discharge_71', 'sensor.symcon_battery_discharge_energy', activeBatteries[0].hasDischargeEnergy);
        }
        if (activeBatteries[1]) {
            addEntity('battery2_soc_184', 'sensor.symcon_battery2_soc', activeBatteries[1].hasSoc);
            addEntity('battery2_power_190', 'sensor.symcon_battery2_power', activeBatteries[1].hasPower);
            if (full) {
                entities['battery2_current_191'] =
                    activeBatteries[1].hasCurrent
                        ? 'sensor.symcon_battery2_current'
                        : 'none';
            } else {
                addEntity(
                    'battery2_current_191',
                    'sensor.symcon_battery2_current',
                    activeBatteries[1].hasCurrent
                );
            }
            addEntity('battery2_voltage_183', 'sensor.symcon_battery2_voltage', activeBatteries[1].hasVoltage);
            addEntity('battery2_temp_182', 'sensor.symcon_battery2_temperature', activeBatteries[1].hasTemperature);
            addEntity(
                'battery2_status',
                'sensor.symcon_battery2_status',
                activeBatteries[1].hasStatus
            );
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
                    !!auxGroups[0]?.hasSoc &&
                    auxGroups[0]?.isWallbox !== true
                );
                addEntity('aux_load2', 'sensor.symcon_aux2', true);
                addEntity(
                    'aux_load2_extra',
                    'sensor.symcon_aux2_extra',
                    !!auxGroups[1]?.hasSoc &&
                    auxGroups[1]?.isWallbox !== true
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

        const auxDisplayName = group => {
            if (!group) return '';

            const name = String(group.name || '').trim();

            // Nur bei der als Wallbox markierten AUX-Last den Fahrzeug-SOC
            // direkt hinter dem Verbrauchernamen anzeigen.
            if (
                auxGroups.length === 2 &&
                group.isWallbox === true &&
                group.hasSoc === true
            ) {
                const rawSoc = String(group.socText || '').trim();

                if (rawSoc !== '') {
                    const percentMatch =
                        rawSoc.match(/([-+]?\d+(?:[.,]\d+)?)\s*%/);

                    const soc = percentMatch
                        ? `${percentMatch[1]}%`
                        : rawSoc;

                    return [name, soc]
                        .filter(Boolean)
                        .join(' · ');
                }
            }

            return name;
        };

        const cfg = {
            cardstyle: cardStyle,
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
                // Die Solar-Tagesenergie gehört auch in der Compact-Ansicht
                // in die obere PV-Anzeige. Nur die übrigen Detailenergien
                // bleiben über showEnergyDetails in Compact ausgeblendet.
                show_daily: entityAvailable(d, 'inverterDailyEnergy'),
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
                additional_loads:
                    window.__symconFullThreeConsumers
                        ? 4
                        : activeGroups.length,

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
                        ? (auxDisplayName(auxGroups[0]) || 'Aux1')
                        : '',
                aux_load2_name:
                    auxGroups.length >= 2
                        ? (auxDisplayName(auxGroups[1]) || 'Aux2')
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
        // Exakt dieselbe AUX-Logik wie in createSunsynkConfig:
        // Nur Full / Full Wide besitzt einen separaten AUX-Bereich.
        // In Compact / Lite werden AUX-markierte Einträge als normale
        // Verbraucher behandelt.
        const fullLayout =
            currentTechnicalLayout.startsWith('full');

        const auxGroups = fullLayout
            ? configuredConsumers
                .filter(group => group.isAux === true)
                .slice(0, 2)
            : [];

        // Außerhalb von Full fallen AUX-markierte Einträge damit ganz normal
        // durch den Verbraucherfilter samt Anzeigeschwelle.
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

        // Nur für Full / Full Wide:
        // AUX-Verbraucher werden separat dargestellt und dürfen deshalb
        // nicht nochmals in der angezeigten Hausleistung/-energie enthalten sein.
        // Die zugrunde liegenden Payload-Werte und damit die berechneten
        // IP-Symcon-Variablen bleiben vollständig unverändert.
        const auxTotalPower = auxGroups.reduce(
            (sum, group) =>
                sum + Math.max(Number(group.value || 0), 0),
            0
        );

        const auxTotalEnergy = auxGroups.reduce(
            (sum, group) =>
                sum + (
                    group.hasDaily
                        ? Math.max(Number(group.dailyValue || 0), 0)
                        : 0
                ),
            0
        );

        const visualHousePower = fullLayout
            ? Math.max(Number(haus || 0) - auxTotalPower, 0)
            : Math.max(Number(haus || 0), 0);

        const visualHouseEnergy = fullLayout
            ? Math.max(
                Number(d.houseEnergy || 0) - auxTotalEnergy,
                0
            )
            : Math.max(Number(d.houseEnergy || 0), 0);

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
            'sensor.symcon_home': ssState(visualHousePower, 'W'),
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
            'sensor.symcon_load_energy': ssState(
                visualHouseEnergy,
                'kWh'
            ),
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


    function removeUnusedFourthConsumerForThree(card) {
        if (
            !window.__symconFullThreeConsumers ||
            !card ||
            !card.shadowRoot
        ) {
            return;
        }

        const roots = getOpenShadowRoots(card.shadowRoot);

        const hideNode = node => {
            if (!node) return;
            node.setAttribute?.('display', 'none');
            node.style?.setProperty('display', 'none', 'important');
            node.style?.setProperty('visibility', 'hidden', 'important');
            node.style?.setProperty('opacity', '0', 'important');
            node.style?.setProperty('pointer-events', 'none', 'important');
        };

        for (const root of roots) {
            // Leistungswert und eventueller Zusatzwert des vierten Loads.
            root.querySelectorAll?.(
                '#ess_load4_value, #ess_load4_value_extra'
            ).forEach(hideNode);

            // Der vierte Name teilt sich in der Original-Card teilweise
            // dieselbe ID mit dem dritten. Der rechte Text (x >= 412)
            // gehört zum vierten Slot.
            root.querySelectorAll?.('[id="ess-load4"]').forEach(node => {
                const x = Number(node.getAttribute?.('x'));
                if (Number.isFinite(x) && x >= 412) {
                    hideNode(node);
                }
            });

            // Auch die beiden unteren Rahmen haben in der Original-Card
            // teilweise dieselbe ID. Nur der rechte Rahmen ist Slot 4.
            root.querySelectorAll?.('rect[id="es-load4"]').forEach(rect => {
                const x = Number(rect.getAttribute?.('x'));
                if (Number.isFinite(x) && x >= 412) {
                    hideNode(rect);
                }
            });

            // Standard-ha-icon des vierten Slots samt foreignObject entfernen.
            root.querySelectorAll?.(
                'ha-icon.essload4-small-icon, .essload4-small-icon'
            ).forEach(icon => {
                const foreignObject = icon.closest?.('foreignObject');
                if (foreignObject) {
                    const iconId = foreignObject.dataset?.symconIconId;
                    hideNode(foreignObject);

                    if (iconId) {
                        root.querySelectorAll?.(
                            `[data-symcon-native-icon="${iconId}"]`
                        ).forEach(hideNode);
                    }
                }

                hideNode(icon);
            });

            // Falls unser Font-Awesome-Ersatz bereits als natives SVG
            // aus dem foreignObject herauskopiert wurde, den rechten
            // Load4-Klon ebenfalls sicher entfernen.
            root.querySelectorAll?.('[data-symcon-native-icon]').forEach(node => {
                try {
                    const box = node.getBBox?.();
                    if (
                        box &&
                        box.x >= 420 &&
                        box.y >= 110 &&
                        box.y <= 180
                    ) {
                        hideNode(node);
                    }
                } catch (_) {}
            });
        }
    }


    function positionLiteDailyEnergyAtCardPosition(card) {
        if (!card || !card.shadowRoot) return;

        const isLite =
            currentTechnicalLayout === 'lite' ||
            currentTechnicalLayout === 'lite-wide';

        if (!isLite) return;

        const roots = getOpenShadowRoots(card.shadowRoot);

        for (const root of roots) {
            const valueNodes = root.querySelectorAll?.(
                '[id="daily_load_value"]'
            ) || [];

            valueNodes.forEach(node => {
                node.setAttribute?.('x', '350');
                node.setAttribute?.('y', '175');

                node.querySelectorAll?.('tspan').forEach(tspan => {
                    tspan.setAttribute?.('x', '350');
                });
            });

            const labelNodes = root.querySelectorAll?.(
                '[id="daily_load"]'
            ) || [];

            labelNodes.forEach(node => {
                node.setAttribute?.('x', '350');
                node.setAttribute?.('y', '189');

                node.querySelectorAll?.('tspan').forEach(tspan => {
                    tspan.setAttribute?.('x', '350');
                });
            });
        }
    }

    function applySunsynkVisualFixes(card, d = null) {
        if (!card) return;

        applyAdditionalLoadColours(card);
        removeUnusedFourthConsumerForThree(card);
        alignConsumerNamesToPowerBoxes(card);
        applyAdditionalLoadWattColourByGeometry(card);
        applyHouseLoadWattColour(card, d);
        applyDynamicHouseSourceIcon(card, d);
        applyInverterVisualColour(card, d);
        showInverterPowerAboveVoltages(card, d);
        positionLiteDailyEnergyAtCardPosition(card);
        compactBatteryValues(card, d);
        compactSmartMeterValues(card, d);
        compactInverterValues(card, d);
        applyConfiguredBatteryStatus(card, d);
        applyAuxVisualOverrides(card, d);
    }

    async function applySunsynkViewOverrides(card, d = null) {
        if (!card) return;
        await card.updateComplete;

        // Ein gemeinsamer Durchlauf für alle visuellen Nachkorrekturen.
        applySunsynkVisualFixes(card, d);

        // Lit rendert bei jeder neuen hass-Zuweisung Teile des Shadow-DOM neu.
        // Nach tatsächlichen DOM-Änderungen die visuellen Korrekturen erneut
        // gesammelt anwenden. Zusätzliche Timer sind dafür nicht mehr nötig.
        if (!card.__symconVisualObserver && card.shadowRoot) {
            let scheduled = false;

            card.__symconVisualObserver = new MutationObserver(() => {
                if (scheduled) return;
                scheduled = true;

                requestAnimationFrame(() => {
                    scheduled = false;

                    applySunsynkVisualFixes(
                        card,
                        card.__symconLastData || d
                    );

                    // Die Sunsynk-Card rendert Autarkie und Verhältnis bei
                    // Änderungen ihres Shadow-DOM teilweise erneut. Deshalb
                    // unsere getrennten Werte nach jedem Renderdurchlauf
                    // wieder einsetzen.
                    const ratioContext =
                        card.__symconRatioContext;

                    if (ratioContext) {
                        applySunsynkRatios(
                            card,
                            ratioContext.d,
                            ratioContext.grid,
                            ratioContext.haus,
                            ratioContext.pvs,
                            ratioContext.batteries
                        );
                    }
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

            const raw = String(group.socText || '').trim();
            if (raw === '') return '';

            const percentMatch =
                raw.match(/([-+]?\d+(?:[.,]\d+)?)\s*%/);

            return percentMatch
                ? `${percentMatch[1]}%`
                : raw;
        };

        const setLabel = (selector, group, includeSoc = false) => {
            const node = findNode(selector);
            if (!node || !group) return;

            const name = String(group.name || '').trim();
            const soc =
                includeSoc && group.isWallbox === true
                    ? cleanSoc(group)
                    : '';
            const wanted = [name, soc].filter(Boolean).join(' · ');

            if (
                wanted !== '' &&
                String(node.textContent || '').trim() !== wanted
            ) {
                node.textContent = wanted;
            }
        };

        if (configuredAux.length === 1) {
            // Auch beim einzelnen AUX-Verbraucher den Fahrzeug-SOC
            // ausschließlich direkt hinter dem Wallbox-Namen anzeigen.
            setLabel(
                '#aux_one',
                configuredAux[0],
                configuredAux[0]?.isWallbox === true
            );

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
            setLabel(
                '#aux_load1',
                configuredAux[0],
                configuredAux[0]?.isWallbox === true
            );
            setLabel(
                '#aux_load2',
                configuredAux[1],
                configuredAux[1]?.isWallbox === true
            );

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

            // Der Wallbox-SOC steht bereits direkt hinter dem Namen.
            // Deshalb alle separaten SOC-/Zusatztexte der Original-Card
            // für genau diesen AUX entfernen, damit er nicht zusätzlich
            // vor dem Namen erscheint.
            [
                {
                    group: configuredAux[0],
                    selectors: [
                        '#aux_load1_extra',
                        '#aux_load1_soc',
                        '[id*="aux_load1"][id*="soc"]',
                        '[id*="aux_load1"][id*="extra"]'
                    ]
                },
                {
                    group: configuredAux[1],
                    selectors: [
                        '#aux_load2_extra',
                        '#aux_load2_soc',
                        '[id*="aux_load2"][id*="soc"]',
                        '[id*="aux_load2"][id*="extra"]'
                    ]
                }
            ].forEach(definition => {
                if (definition.group?.isWallbox !== true) return;

                definition.selectors.forEach(selector => {
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
                            node.setAttribute?.(
                                'visibility',
                                'hidden'
                            );
                        });
                    }
                });
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



    function compactBatteryValues(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        const roots = getOpenShadowRoots(card.shadowRoot);
        const batteries = Array.isArray(d.batteries)
            ? d.batteries
            : [];

        if (!batteries.length) return;

        const isFull =
            currentTechnicalLayout === 'full' ||
            currentTechnicalLayout === 'full-wide';

        const visibleNode = node => {
            if (!node) return false;

            if (
                node.getAttribute?.('display') === 'none' ||
                node.getAttribute?.('visibility') === 'hidden' ||
                node.classList?.contains('st12')
            ) {
                return false;
            }

            try {
                const style = getComputedStyle(node);
                if (
                    style.display === 'none' ||
                    style.visibility === 'hidden' ||
                    Number(style.opacity) === 0
                ) {
                    return false;
                }
            } catch (_) {}

            try {
                const box = node.getBoundingClientRect?.();
                return !!box && box.width > 0 && box.height > 0;
            } catch (_) {
                return false;
            }
        };

        const rememberTextY = node => {
            if (!node.dataset.symconBatteryOriginalY) {
                node.dataset.symconBatteryOriginalY =
                    String(node.getAttribute?.('y') || '0');
            }
        };

        const rememberFrame = frame => {
            if (!frame.dataset.symconBatteryOriginalY) {
                frame.dataset.symconBatteryOriginalY =
                    String(frame.getAttribute?.('y') || '0');
            }
            if (!frame.dataset.symconBatteryOriginalHeight) {
                frame.dataset.symconBatteryOriginalHeight =
                    String(frame.getAttribute?.('height') || '0');
            }
        };

        const restoreFrame = frame => {
            rememberFrame(frame);
            frame.setAttribute?.(
                'y',
                frame.dataset.symconBatteryOriginalY
            );
            frame.setAttribute?.(
                'height',
                frame.dataset.symconBatteryOriginalHeight
            );
        };

        const measurementDefs = [
            {
                batteryIndex: 0,
                selectors: [
                    '[id="battery_voltage_183"]',
                    '[id="battery1_voltage_183"]'
                ],
                available: battery =>
                    battery?.hasVoltage === true
            },
            {
                batteryIndex: 0,
                selectors: [
                    '[id="battery_current_191"]',
                    '[id="battery1_current_191"]'
                ],
                available: battery =>
                    battery?.hasCurrent === true
            },
            {
                batteryIndex: 0,
                selectors: [
                    '[id="data.batteryPower_190"]',
                    '[id="battery_power_190"]',
                    '[id="batteryPower_190"]'
                ],
                available: battery =>
                    battery?.hasPower === true
            },
            {
                batteryIndex: 1,
                selectors: [
                    '[id="battery2_voltage_183"]'
                ],
                available: battery =>
                    battery?.hasVoltage === true
            },
            {
                batteryIndex: 1,
                selectors: [
                    '[id="battery2_current_191"]'
                ],
                available: battery =>
                    battery?.hasCurrent === true
            },
            {
                batteryIndex: 1,
                selectors: [
                    '[id="data.battery2Power_190"]',
                    '[id="battery2_power_190"]',
                    '[id="battery2Power_190"]'
                ],
                available: battery =>
                    battery?.hasPower === true
            }
        ];

        const setMissingHidden = (node, hidden) => {
            if (hidden) {
                node.setAttribute?.('display', 'none');
                node.style?.setProperty(
                    'display',
                    'none',
                    'important'
                );
            } else {
                node.removeAttribute?.('display');
                node.style?.removeProperty('display');
            }
        };

        // Compact / Large(Lite) haben stabile Batterie-Container.
        // Diese Logik hat bereits zuverlässig funktioniert und wird deshalb
        // bewusst getrennt vom Full-Layout behandelt.
        const processStableBox = (
            root,
            boxSelector,
            battery,
            defs
        ) => {
            if (!battery) return;

            const boxSvg = root.querySelector?.(boxSelector);
            if (!boxSvg) return;

            const rects = Array.from(
                boxSvg.querySelectorAll?.(':scope > rect') || []
            );

            const rows = [];

            defs.forEach(def => {
                const available = def.available(battery);

                const candidates = [];
                def.selectors.forEach(selector => {
                    boxSvg.querySelectorAll?.(selector)
                        .forEach(node => {
                            if (!candidates.includes(node)) {
                                candidates.push(node);
                            }
                        });
                });

                candidates.forEach(rememberTextY);

                const node =
                    candidates.find(visibleNode) ||
                    candidates[0] ||
                    null;

                if (!node) return;

                setMissingHidden(node, !available);

                if (available) {
                    node.removeAttribute?.('display');
                    node.style?.removeProperty('display');
                    rows.push({
                        node,
                        y: Number(
                            node.dataset.symconBatteryOriginalY ||
                            node.getAttribute?.('y') ||
                            0
                        )
                    });
                }
            });

            if (!rects.length) return;
            rects.forEach(restoreFrame);

            if (!rows.length) {
                // Ohne V/A/W gibt es auch keinen sinnvollen Datenrahmen.
                // Nur den Rahmen ausblenden, der im selben Bereich sitzt.
                rects.forEach(frame => {
                    try {
                        const fr = frame.getBoundingClientRect?.();
                        const cr = boxSvg.getBoundingClientRect?.();
                        if (
                            fr &&
                            cr &&
                            fr.width >= 35 &&
                            fr.height >= 18 &&
                            fr.left >= cr.left - 2 &&
                            fr.right <= cr.right + 2
                        ) {
                            frame.style?.setProperty(
                                'display',
                                'none',
                                'important'
                            );
                        }
                    } catch (_) {}
                });
                return;
            }

            rows.sort((a, b) => a.y - b.y);
            const rowCenter =
                (rows[0].y + rows[rows.length - 1].y) / 2;

            let frame = null;
            let best = Number.POSITIVE_INFINITY;

            rects.forEach(candidate => {
                candidate.style?.removeProperty('display');

                const y = Number(
                    candidate.dataset.symconBatteryOriginalY || 0
                );
                const h = Number(
                    candidate.dataset.symconBatteryOriginalHeight || 0
                );

                if (
                    !Number.isFinite(y) ||
                    !Number.isFinite(h) ||
                    h <= 0
                ) {
                    return;
                }

                const centre = y + h / 2;
                const contains =
                    rowCenter >= y - 8 &&
                    rowCenter <= y + h + 8;
                const score =
                    Math.abs(centre - rowCenter) +
                    (contains ? 0 : 1000);

                if (score < best) {
                    best = score;
                    frame = candidate;
                }
            });

            if (!frame) return;

            const originalY = Number(
                frame.dataset.symconBatteryOriginalY
            );
            const originalH = Number(
                frame.dataset.symconBatteryOriginalHeight
            );
            const centre = originalY + originalH / 2;

            // Rahmen bewusst luftiger als bisher:
            // 1 Zeile 32, 2 Zeilen 51, 3 Zeilen 70.
            const spacing = 19;
            const newHeight =
                32 + ((rows.length - 1) * spacing);
            const newY = centre - newHeight / 2;

            frame.setAttribute?.('y', String(newY));
            frame.setAttribute?.(
                'height',
                String(newHeight)
            );

            const firstY =
                centre - ((rows.length - 1) * spacing / 2);

            rows.forEach((row, index) => {
                row.node.setAttribute?.(
                    'y',
                    String(firstY + index * spacing)
                );
            });
        };

        // Full hat je nach 1 oder 2 Batterien eine andere Original-Geometrie.
        // Darum wird hier nicht mit festen x/y-Werten gearbeitet. Stattdessen
        // wird der tatsächlich sichtbare Rahmen ermittelt, der die sichtbaren
        // Batterie-V/A/W-Texte umschließt. Dadurch funktioniert derselbe Code
        // für beide Full-Varianten.
        const processFull = root => {
            const measurements = [];

            measurementDefs.forEach(def => {
                const battery = batteries[def.batteryIndex];
                if (!battery) return;

                const available = def.available(battery);
                const candidates = [];

                def.selectors.forEach(selector => {
                    root.querySelectorAll?.(selector)
                        .forEach(node => {
                            if (!candidates.includes(node)) {
                                candidates.push(node);
                            }
                        });
                });

                candidates.forEach(rememberTextY);

                // In Full existieren mehrere alternative Elemente mit
                // denselben IDs. Nur die aktuell gerenderte Variante zählt.
                candidates.forEach(node => {
                    if (!visibleNode(node)) return;

                    setMissingHidden(node, !available);
                    if (!available) return;

                    let screen;
                    try {
                        screen = node.getBoundingClientRect?.();
                    } catch (_) {
                        screen = null;
                    }

                    if (
                        !screen ||
                        screen.width <= 0 ||
                        screen.height <= 0
                    ) {
                        return;
                    }

                    measurements.push({
                        node,
                        batteryIndex: def.batteryIndex,
                        screen,
                        cx: screen.left + screen.width / 2,
                        cy: screen.top + screen.height / 2
                    });
                });
            });

            if (!measurements.length) return;

            const allRects = Array.from(
                root.querySelectorAll?.('rect') || []
            );

            const frameCandidates = [];

            allRects.forEach(frame => {
                if (!visibleNode(frame)) return;

                rememberFrame(frame);
                restoreFrame(frame);

                let fr;
                try {
                    fr = frame.getBoundingClientRect?.();
                } catch (_) {
                    fr = null;
                }

                if (
                    !fr ||
                    fr.width < 35 ||
                    fr.height < 20 ||
                    fr.width > 240 ||
                    fr.height > 180
                ) {
                    return;
                }

                const inside = measurements.filter(item =>
                    item.cx >= fr.left + 2 &&
                    item.cx <= fr.right - 2 &&
                    item.cy >= fr.top - 6 &&
                    item.cy <= fr.bottom + 6
                );

                if (!inside.length) return;

                // Ein Datenrahmen muss deutlich breiter als der Text selbst
                // sein. Dadurch fallen kleine Hintergrund-/Statusrechtecke weg.
                const minTextLeft = Math.min(
                    ...inside.map(item => item.screen.left)
                );
                const maxTextRight = Math.max(
                    ...inside.map(item => item.screen.right)
                );

                if (
                    fr.left > minTextLeft - 4 ||
                    fr.right < maxTextRight + 4
                ) {
                    return;
                }

                frameCandidates.push({
                    frame,
                    rect: fr,
                    inside,
                    area: fr.width * fr.height
                });
            });

            if (!frameCandidates.length) return;

            // Jedem Messwert den kleinsten Rahmen zuordnen, der ihn umschließt.
            // Bei einer Batterie ergibt das den einzelnen Full-Rahmen; bei zwei
            // Batterien automatisch die von der Card verwendete zweite Geometrie.
            const frameMap = new Map();

            measurements.forEach(item => {
                const matches = frameCandidates
                    .filter(candidate =>
                        candidate.inside.some(
                            inside => inside.node === item.node
                        )
                    )
                    .sort((a, b) => a.area - b.area);

                if (!matches.length) return;

                const selected = matches[0];
                if (!frameMap.has(selected.frame)) {
                    frameMap.set(selected.frame, {
                        candidate: selected,
                        nodes: []
                    });
                }

                frameMap.get(selected.frame).nodes.push(item);
            });

            frameMap.forEach(group => {
                const frame = group.candidate.frame;
                const nodes = group.nodes;

                if (!nodes.length) return;

                // Zeilen anhand der realen Y-Position gruppieren.
                // Bei zwei Batterien dürfen zwei Werte nebeneinander in
                // derselben Zeile stehen und behalten ihre X-Position.
                const ordered = [...nodes].sort(
                    (a, b) => a.cy - b.cy
                );
                const rows = [];

                ordered.forEach(item => {
                    let row = rows.find(
                        existing =>
                            Math.abs(existing.screenY - item.cy) <= 5
                    );

                    if (!row) {
                        row = {
                            screenY: item.cy,
                            items: []
                        };
                        rows.push(row);
                    }

                    row.items.push(item);
                });

                rows.sort(
                    (a, b) => a.screenY - b.screenY
                );

                const originalY = Number(
                    frame.dataset.symconBatteryOriginalY
                );
                const originalH = Number(
                    frame.dataset.symconBatteryOriginalHeight
                );

                if (
                    !Number.isFinite(originalY) ||
                    !Number.isFinite(originalH) ||
                    originalH <= 0
                ) {
                    return;
                }

                const centre =
                    originalY + originalH / 2;

                // Gleiche luftige Proportion wie Compact/Large.
                const spacing = 19;
                const newHeight =
                    32 + ((rows.length - 1) * spacing);
                const newY =
                    centre - newHeight / 2;

                frame.setAttribute?.(
                    'y',
                    String(newY)
                );
                frame.setAttribute?.(
                    'height',
                    String(newHeight)
                );

                const firstY =
                    centre -
                    ((rows.length - 1) * spacing / 2);

                rows.forEach((row, index) => {
                    row.items.forEach(item => {
                        item.node.setAttribute?.(
                            'y',
                            String(
                                firstY +
                                index * spacing
                            )
                        );
                    });
                });
            });
        };

        for (const root of roots) {
            if (isFull) {
                processFull(root);
                continue;
            }

            const bat1 = batteries[0];
            const bat2 = batteries[1];

            processStableBox(
                root,
                '#battery_data',
                bat1,
                measurementDefs.filter(
                    def => def.batteryIndex === 0
                )
            );

            processStableBox(
                root,
                '#battery2_data_lite',
                bat2,
                measurementDefs.filter(
                    def => def.batteryIndex === 1
                )
            );
        }
    }

    function compactSmartMeterValues(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        // Dynamische Box-Geometrie ausschließlich in Full / Full Wide.
        // Compact und Lite bleiben vollständig bei der Original-Sunsynk-Geometrie.
        const isFullLayout =
            currentTechnicalLayout === 'full' ||
            currentTechnicalLayout === 'full-wide';
        if (!isFullLayout) return;

        const roots = getOpenShadowRoots(card.shadowRoot);

        // Die Originalkarte reserviert feste Y-Positionen für L1/L2/L3,
        // Frequenz und Gesamtleistung. Fehlt dazwischen ein Wert, entsteht
        // deshalb optisch eine Leerzeile. Wir ordnen ausschließlich die
        // tatsächlich konfigurierten Werte neu und zentrieren sie in der
        // bestehenden Smartmeter-Box. Inhalt und Box-Geometrie bleiben gleich.
        const wanted = [
            ['inverter_voltage_154', entityAvailable(d, 'gridVoltageL1')],
            ['inverter_voltage_L2', entityAvailable(d, 'gridVoltageL2')],
            ['inverter_voltage_L3', entityAvailable(d, 'gridVoltageL3')],
            ['load_frequency_192', entityAvailable(d, 'gridFrequency')],
            ['grid_power_169', true]
        ];

        for (const root of roots) {
            const visible = [];

            for (const [id, available] of wanted) {
                const node = root.querySelector?.(`#${id}`) || null;
                if (!node) continue;

                if (!available) {
                    node.setAttribute?.('display', 'none');
                    node.style?.setProperty('display', 'none', 'important');
                    continue;
                }

                node.removeAttribute?.('display');
                node.style?.removeProperty('display');
                visible.push(node);
            }

            if (!visible.length) continue;

            // Smartmeter-Box: y=153..223. Die Originalkarte verwendet bei
            // fünf Zeilen 164/177/190/203/216 (= 13 px Abstand). Genau diesen
            // Abstand behalten wir bei und zentrieren weniger Zeilen vertikal.
            const spacing = 13;
            const centreY = 190;
            const firstY = centreY - ((visible.length - 1) * spacing / 2);

            visible.forEach((node, index) => {
                node.setAttribute?.('y', String(firstY + index * spacing));
                node.removeAttribute?.('transform');
            });

            // Auch der Smartmeter-Rahmen selbst folgt nun der Anzahl der
            // tatsächlich sichtbaren Werte. Die Originalbox liegt bei
            // x=234, y=153, width=70, height=70 und ist damit auf y=188
            // zentriert. Diese Flussachse bleibt unverändert, damit die
            // horizontalen Netzlinien weiterhin exakt in die Box laufen.
            const boxCentreY = 188;
            const boxHeight = Math.max(24, 18 + (visible.length - 1) * spacing);
            const boxY = boxCentreY - boxHeight / 2;

            const gridSvg = root.querySelector?.('#Grid');
            let meterBox = null;

            if (gridSvg) {
                // Die Smartmeter-Box ist der 70x70-Rahmen bei x=234.
                // Nicht über die Reihenfolge der übrigen Grid-Rechtecke gehen,
                // damit Non-Essential-Load-Boxen unberührt bleiben.
                meterBox = Array.from(gridSvg.querySelectorAll?.('rect') || [])
                    .find(rect => {
                        const x = Number(rect.getAttribute?.('x'));
                        const width = Number(rect.getAttribute?.('width'));
                        return Math.abs(x - 234) < 0.5 && Math.abs(width - 70) < 0.5;
                    }) || null;
            }

            if (meterBox) {
                meterBox.setAttribute?.('y', String(boxY));
                meterBox.setAttribute?.('height', String(boxHeight));
            }
        }
    }

    function compactInverterValues(card, d) {
        if (!card || !card.shadowRoot || !d) return;

        // Dynamische Box-Geometrie ausschließlich in Full / Full Wide.
        // Compact und Lite bleiben vollständig bei der Original-Sunsynk-Geometrie.
        const isFullLayout =
            currentTechnicalLayout === 'full' ||
            currentTechnicalLayout === 'full-wide';
        if (!isFullLayout) return;

        const roots = getOpenShadowRoots(card.shadowRoot);

        // Die Originalkarte reserviert in der Wechselrichterbox feste Zeilen
        // für Gesamtleistung sowie die Ströme L1/L2/L3. Nicht konfigurierte
        // Phasen dürfen deshalb keinen sichtbaren Leerplatz hinterlassen.
        // Es werden ausschließlich vorhandene Werte neu angeordnet; Inhalt,
        // Farben und Geometrie der Box bleiben unverändert.
        const wanted = [
            ['inverter_power_175',
                entityAvailable(d, 'inverterPower') ||
                d.inverterPowerAvailable === true],
            ['inverter_current_164', entityAvailable(d, 'inverterCurrentL1')],
            ['inverter_current_L2', entityAvailable(d, 'inverterCurrentL2')],
            ['inverter_current_L3', entityAvailable(d, 'inverterCurrentL3')]
        ];

        for (const root of roots) {
            const visible = [];

            for (const [id, available] of wanted) {
                const node = root.querySelector?.(`#${id}`) || null;
                if (!node) continue;

                if (!available) {
                    node.setAttribute?.('display', 'none');
                    node.style?.setProperty('display', 'none', 'important');
                    continue;
                }

                node.removeAttribute?.('display');
                node.style?.removeProperty('display');
                visible.push(node);
            }

            if (!visible.length) continue;

            // Die WR-Werte liegen im Original ungefähr im Bereich y=174..214.
            // Den vorhandenen 13-px-Zeilenabstand behalten wir bei. Zusätzlich
            // wird nun auch der Rahmen selbst auf die tatsächlich sichtbaren
            // Zeilen verkleinert bzw. vergrößert.
            const spacing = 13;

            // Die seitliche Netz-/Smartmeter-Flusslinie liegt in der
            // Originalkarte auf y=187. Die dynamische WR-Box bleibt deshalb
            // unabhängig von ihrer Höhe exakt auf dieser Flussachse zentriert.
            const centreY = 187;
            const firstY = centreY - ((visible.length - 1) * spacing / 2);

            visible.forEach((node, index) => {
                node.setAttribute?.('y', String(firstY + index * spacing));
                node.removeAttribute?.('transform');
            });

            // Original: x=145.15, y=162, width=70, height=50/60.
            // Pro sichtbarer Zeile werden 13 px benötigt, zusätzlich bleibt
            // oben und unten genügend Innenabstand. Vier Zeilen ergeben damit
            // praktisch wieder die originale 60-px-Box.
            const boxHeight = Math.max(24, 20 + (visible.length - 1) * spacing);
            const boxY = centreY - boxHeight / 2;

            const inverterSvg = root.querySelector?.('#Inverter');
            const box =
                inverterSvg?.querySelector?.(':scope > rect') ||
                root.querySelector?.('#Inverter > rect');

            if (box) {
                box.setAttribute?.('y', String(boxY));
                box.setAttribute?.('height', String(boxHeight));
            }

            // Alle an die WR-Box angrenzenden Flusslinien bis an den
            // tatsächlichen Rahmen führen. Die Originalkarte verwendet oben
            // y=162 und seitlich y=187. Durch die dynamische Höhe ändern sich
            // nur Ober- und Unterkante; die seitliche Achse bleibt y=187.
            const boxTop = boxY;
            const boxBottom = boxY + boxHeight;

            const inverterPath =
                root.querySelector?.('#inverter-path') ||
                inverterSvg?.querySelector?.('#inverter-path');

            if (inverterPath) {
                const current = String(inverterPath.getAttribute?.('d') || '');
                // X-Koordinate aus dem Originalpfad beibehalten (wichtig für
                // normale und Wide-Darstellung), nur den Start-Y anpassen.
                const match = current.match(/^\s*M\s*([\d.]+)\s+[\d.]+\s+L\s*([\d.]+)\s+([\d.]+)/i);
                if (match) {
                    inverterPath.setAttribute?.(
                        'd',
                        `M ${match[1]} ${boxBottom} L ${match[2]} ${match[3]}`
                    );
                }
            }

            // Obere Essential-Load-Leitung: ihr letztes Segment endet im
            // Original an y=162. Dieses Ende auf die neue Oberkante setzen.
            const essentialPath = root.querySelector?.('#es-line');
            if (essentialPath) {
                const current = String(essentialPath.getAttribute?.('d') || '');
                const updated = current.replace(
                    /(L\s*[\d.]+\s+)[\d.]+\s*$/i,
                    `$1${boxTop}`
                );
                if (updated !== current) {
                    essentialPath.setAttribute?.('d', updated);
                }
            }

            // Falls AUX aktiv ist, startet auch dessen zweite Flusslinie an
            // der WR-Oberkante. Nur den ersten M-Y-Wert ersetzen.
            const auxPath = root.querySelector?.('#aux-line2');
            if (auxPath) {
                const current = String(auxPath.getAttribute?.('d') || '');
                const updated = current.replace(
                    /^(\s*M\s*[\d.]+\s+)[\d.]+/i,
                    `$1${boxTop}`
                );
                if (updated !== current) {
                    auxPath.setAttribute?.('d', updated);
                }
            }
        }
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

            // Tages-Eigenverbrauch inklusive Batterie:
            //
            // Eigene abgegebene Energie =
            // PV-Tagesenergie
            // + Batterieentladung heute
            // - Batterieladung heute.
            //
            // Der selbst genutzte Anteil ist die Hausenergie abzüglich
            // des Netzbezugs. Damit wird auch eine Batterieentladung in der
            // Nacht berücksichtigt.
            const batteryChargeEnergy = (
                Array.isArray(batteries) ? batteries : []
            ).reduce(
                (sum, battery) =>
                    sum + Math.max(
                        Number(battery?.chargeEnergy || 0),
                        0
                    ),
                0
            );

            const batteryDischargeEnergy = (
                Array.isArray(batteries) ? batteries : []
            ).reduce(
                (sum, battery) =>
                    sum + Math.max(
                        Number(battery?.dischargeEnergy || 0),
                        0
                    ),
                0
            );

            const ownDeliveredEnergy = Math.max(
                pvEnergy
                + batteryDischargeEnergy
                - batteryChargeEnergy,
                0
            );

            const selfSuppliedHouseEnergy = Math.max(
                houseEnergy - Math.max(gridImportEnergy, 0),
                0
            );

            selfConsumption = ownDeliveredEnergy > 0
                ? clampPercent(
                    (
                        selfSuppliedHouseEnergy /
                        ownDeliveredEnergy
                    ) * 100
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

        const autarkyText = `${autarky}%`;
        const selfConsumptionText = `${selfConsumption}%`;

        // Nur echte Änderungen schreiben. Das verhindert, dass der
        // MutationObserver durch unsere eigenen identischen Werte dauerhaft
        // erneut ausgelöst wird.
        if (
            autarkyValue &&
            autarkyValue.textContent !== autarkyText
        ) {
            autarkyValue.textContent = autarkyText;
        }

        if (
            ratioValue &&
            ratioValue.textContent !== selfConsumptionText
        ) {
            ratioValue.textContent = selfConsumptionText;
        }

        if (
            autarkyLabel &&
            autarkyLabel.textContent !== 'Autarkie'
        ) {
            autarkyLabel.textContent = 'Autarkie';
        }

        if (
            ratioLabel &&
            ratioLabel.textContent !== 'Eigenverbrauch'
        ) {
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
            card.__symconRatioContext = {
                d,
                grid,
                haus,
                pvs,
                batteries
            };
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
        updateTechnicalLayoutButtons();
        if (!sunsynkCard) {
            sunsynkPending = [d, grid, haus, pvs, batteries, wallbox, groups];
            ensureSunsynkCard(d, grid, haus, pvs, batteries, wallbox, groups).catch(() => {});
            return;
        }
        window.__symconHasWallbox = !!d.hasWallbox;
        sunsynkCard.__symconLastData = d;
        sunsynkCard.__symconRatioContext = {
            d,
            grid,
            haus,
            pvs,
            batteries
        };
        sunsynkCard.setConfig(createSunsynkConfig(d, pvs, batteries, wallbox, groups));
        sunsynkCard.hass = createSunsynkHass(d, grid, haus, pvs, batteries, wallbox, groups);
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

    /*
     * Browserzustand nach demselben Prinzip wie beim Wärmepumpenmodul:
     *
     * Nicht schon beim Parsen des HTML auf localStorage zugreifen, sondern
     * genau einmal beim Eintreffen des ersten echten Payloads. Erst danach
     * wird die Karte mit setState() aufgebaut.
     *
     * Dadurch ist der Ablauf:
     *   Payload -> Browserzustand laden -> Karte aufbauen
     *
     * Datenupdates ändern die lokal gewählte Ansicht anschließend nicht mehr.
     */
    const VIEW_STORAGE_KEY = 'symcon-energiefluss-view';
    const TECHNICAL_LAYOUT_STORAGE_KEY =
        'symcon-energiefluss-technical-layout';

    let currentDisplayMode = 'flow';
    let currentTechnicalLayout = 'lite';
    let browserViewStateInitialized = false;

    function initializeBrowserViewState() {
        /*
         * Einheitliche Speicherung in Browser UND Symcon-App:
         * Eine Energiefluss-Instanz = ein eigener gespeicherter Zustand.
         *
         * Beispiel:
         * /visu/36446/ -> instance-36446
         */
        const instanceScope = getInstanceStorageScope();

        if (instanceScope) {
            const instanceViewKey =
                `symcon-energiefluss-${instanceScope}-view`;
            const instanceLayoutKey =
                `symcon-energiefluss-${instanceScope}-technical-layout`;

            const storedView = window.localStorage.getItem(instanceViewKey);
            const storedLayout = window.localStorage.getItem(instanceLayoutKey);

            if (storedView === 'flow' || storedView === 'house') {
                currentDisplayMode = storedView;
            }

            if (
                storedLayout === 'compact'
                || storedLayout === 'compact-wide'
                || storedLayout === 'lite'
                || storedLayout === 'lite-wide'
                || storedLayout === 'full'
                || storedLayout === 'full-wide'
            ) {
                currentTechnicalLayout = storedLayout;
            }

            widgetStorageScope = instanceScope;
            widgetViewStorageKey = instanceViewKey;
            widgetTechnicalLayoutStorageKey = instanceLayoutKey;
            widgetDetectionReady = true;

            updateTechnicalLayoutButtons();
            updateDisplayModeButton();
            return;
        }

        // Sicherheitsfallback, falls die Instanz-ID wider Erwarten nicht
        // aus /visu/<ID>/ gelesen werden kann.
        const browserView = window.localStorage.getItem(
            'symcon-energiefluss-view'
        );
        const browserLayout = window.localStorage.getItem(
            'symcon-energiefluss-technical-layout'
        );

        if (browserView === 'flow' || browserView === 'house') {
            currentDisplayMode = browserView;
        }

        if (
            browserLayout === 'compact'
            || browserLayout === 'compact-wide'
            || browserLayout === 'lite'
            || browserLayout === 'lite-wide'
            || browserLayout === 'full'
            || browserLayout === 'full-wide'
        ) {
            currentTechnicalLayout = browserLayout;
        }

        updateTechnicalLayoutButtons();
        updateDisplayModeButton();
    }


    /*
     * Kachelspezifische Ergänzung.
     * Die festen Browser-Keys oben bleiben unverändert und werden sofort
     * wie beim Wärmepumpenmodul geladen.
     */
    let widgetStorageScope = null;
    let widgetViewStorageKey = null;
    let widgetTechnicalLayoutStorageKey = null;
    let widgetDetectionStarted = false;
    let widgetDetectionReady = false;

    /*
     * Symcon-App:
     * Die HTML-Kachel läuft dort als eigenes Top-Level-Dokument. Es gibt
     * deshalb weder frameElement noch Parent-Grid und somit keine Widget-ID.
     *
     * Dafür ist die Zielinstanz sofort aus /visu/<InstanzID>/ bekannt.
     * Pro Energiefluss-Instanz wird deshalb ein eigener Zustand gespeichert.
     */
    function getEnergyFlowInstanceID() {
        try {
            const match = window.location.pathname.match(/\/visu\/(\d+)\/?/);
            return match ? match[1] : null;
        } catch (_) {
            return null;
        }
    }

    function getInstanceStorageScope() {
        const instanceID = getEnergyFlowInstanceID();
        if (!instanceID) {
            return null;
        }

        return `instance-${instanceID}`;
    }

    function getVisualizationStorageScope() {
        const parseMaybeJson = value => {
            if (value == null) return null;
            if (typeof value === 'object') return value;
            try {
                let parsed = JSON.parse(String(value));
                if (typeof parsed === 'string') {
                    parsed = JSON.parse(parsed);
                }
                return parsed;
            } catch (_) {
                return null;
            }
        };

        const getGridConfiguration = () => {
            // Lokale Grid-Konfiguration bevorzugen: Sie entspricht exakt dem
            // Layout dieses Browsers/Geräts.
            try {
                const visuID = new URL(window.location.href).searchParams.get('visuID');
                const keys = Object.keys(window.parent.localStorage || window.localStorage);
                let key = null;
                if (visuID) {
                    key = keys.find(k => k === `flutter.${visuID}-~Desktop-gridConfig`) || null;
                }
                if (!key) {
                    key = keys.find(k => /flutter\.\d+-~Desktop-gridConfig$/.test(k)) || null;
                }
                if (key) {
                    const storage = window.parent.localStorage || window.localStorage;
                    const cfg = parseMaybeJson(storage.getItem(key));
                    if (cfg && typeof cfg === 'object') {
                        return cfg;
                    }
                }
            } catch (_) {
                // Snapshot-Fallback folgt.
            }

            try {
                const server = window.__EF_SERVER_GRID__;
                if (server && server.grid) {
                    return parseMaybeJson(server.grid);
                }
            } catch (_) {
                // Kein Grid verfügbar.
            }
            return null;
        };

        const grid = getGridConfiguration();
        if (!grid || !grid.landscape) {
            return 'widget-fallback';
        }

        try {
            if (!window.parent || window.parent === window || !window.frameElement) {
                return 'widget-standalone';
            }

            const parentDocument = window.parent.document;
            const currentFrame = window.frameElement;
            const frames = Array.from(parentDocument.querySelectorAll('iframe'))
                .filter(frame => {
                    const r = frame.getBoundingClientRect();
                    return r.width > 40 && r.height > 40;
                });

            const currentIndex = frames.indexOf(currentFrame);
            if (currentIndex < 0 || !frames.length) {
                return 'widget-fallback';
            }

            const frameRects = frames.map(frame => {
                const r = frame.getBoundingClientRect();
                const src = frame.getAttribute('src') || '';
                const visuMatch = src.match(/\/visu\/(\d+)\//);
                return {
                    frame,
                    left: r.left,
                    top: r.top,
                    width: r.width,
                    height: r.height,
                    targetID: visuMatch ? Number(visuMatch[1]) : 0
                };
            });

            const serverTargets = (() => {
                try {
                    const t = window.__EF_SERVER_GRID__ && window.__EF_SERVER_GRID__.targets;
                    return t && typeof t === 'object' ? t : {};
                } catch (_) {
                    return {};
                }
            })();

            const landscape = grid.landscape || {};
            const positions = landscape.individualPositions || {};
            const dimensions = landscape.individualDimensions || {};

            let best = null;

            const geometryCost = (fr, widget, transform) => {
                const sx = transform.sx;
                const sy = transform.sy;
                const predicted = {
                    left: transform.ox + widget.left * sx,
                    top: transform.oy + widget.top * sy,
                    width: widget.width * sx,
                    height: widget.height * sy
                };

                // Fehler in Rastereinheiten; so bleibt die Bewertung unabhängig
                // von Auflösung und Browser-Zoom.
                let cost = (
                    Math.abs(fr.left - predicted.left) / Math.max(1, sx) +
                    Math.abs(fr.top - predicted.top) / Math.max(1, sy) +
                    Math.abs(fr.width - predicted.width) / Math.max(1, sx) +
                    Math.abs(fr.height - predicted.height) / Math.max(1, sy)
                );

                // Wenn sowohl iframe als auch Grid-Widget ihr Symcon-Ziel kennen,
                // darf ein anderes Ziel nicht geometrisch "gewinnen".
                if (fr.targetID > 0 && widget.targetID > 0 && fr.targetID !== widget.targetID) {
                    cost += 10000;
                }
                return cost;
            };

            Object.entries(positions).forEach(([containerId, widgetPositions]) => {
                if (!widgetPositions || typeof widgetPositions !== 'object') return;

                const widgets = Object.entries(widgetPositions)
                    .map(([widgetId, pos]) => {
                        const dim = dimensions[widgetId];
                        if (!dim || !pos) return null;
                        const width = Number(dim.width);
                        const height = Number(dim.height);
                        const left = Number(pos.left);
                        const top = Number(pos.top);
                        if (![width, height, left, top].every(Number.isFinite)) return null;
                        const targetID = Number(serverTargets[String(widgetId)] || 0);
                        return {widgetId, width, height, left, top, targetID};
                    })
                    .filter(Boolean);

                if (!widgets.length || widgets.length < frameRects.length) return;

                const currentTargetID = frameRects[currentIndex]?.targetID || 0;
                if (currentTargetID > 0) {
                    const hasCurrentTarget = widgets.some(w => w.targetID === currentTargetID);
                    // Nur anwenden, wenn die serverseitige Zielauflösung für diesen
                    // Container tatsächlich Informationen geliefert hat.
                    const hasKnownTargets = widgets.some(w => w.targetID > 0);
                    if (hasKnownTargets && !hasCurrentTarget) return;
                }

                // Jede Frame/Widget-Kombination einmal als Transformationsanker
                // testen. Der richtige Container erzeugt über alle sichtbaren
                // iframes hinweg einen nahezu identischen Rastermaßstab.
                frameRects.forEach((anchorFrame, anchorFrameIndex) => {
                    widgets.forEach(anchorWidget => {
                        if (anchorFrame.targetID > 0 && anchorWidget.targetID > 0 &&
                            anchorFrame.targetID !== anchorWidget.targetID) return;
                        const sx = anchorFrame.width / Math.max(1, anchorWidget.width);
                        const sy = anchorFrame.height / Math.max(1, anchorWidget.height);
                        if (!Number.isFinite(sx) || !Number.isFinite(sy) || sx < 10 || sy < 10) return;

                        const transform = {
                            sx,
                            sy,
                            ox: anchorFrame.left - anchorWidget.left * sx,
                            oy: anchorFrame.top - anchorWidget.top * sy
                        };

                        const available = new Set(widgets.map((_, i) => i));
                        const assignments = new Array(frameRects.length).fill(null);
                        let totalCost = 0;

                        // Den Anker fest zuordnen, anschließend die übrigen
                        // Frames jeweils dem geometrisch besten freien Widget.
                        const anchorWidgetIndex = widgets.indexOf(anchorWidget);
                        assignments[anchorFrameIndex] = anchorWidget;
                        available.delete(anchorWidgetIndex);
                        totalCost += geometryCost(anchorFrame, anchorWidget, transform);

                        const otherFrameIndexes = frameRects
                            .map((_, i) => i)
                            .filter(i => i !== anchorFrameIndex);

                        for (const fi of otherFrameIndexes) {
                            let bestWidgetIndex = -1;
                            let bestCost = Infinity;
                            for (const wi of available) {
                                const cost = geometryCost(frameRects[fi], widgets[wi], transform);
                                if (cost < bestCost) {
                                    bestCost = cost;
                                    bestWidgetIndex = wi;
                                }
                            }
                            if (bestWidgetIndex < 0) {
                                totalCost += 1000;
                                continue;
                            }
                            assignments[fi] = widgets[bestWidgetIndex];
                            available.delete(bestWidgetIndex);
                            totalCost += bestCost;
                        }

                        // Viele zusätzliche Widgets sind erlaubt, aber ein kleiner
                        // Malus bevorzugt den Container, der die sichtbare Seite
                        // tatsächlich am präzisesten beschreibt.
                        // Ein ähnlich aussehender großer Container einer anderen Seite
                        // darf nicht nur wegen eines Teilmusters gewinnen. Deshalb deutlich
                        // stärker bestrafen, wenn sehr viele zusätzliche Widgets vorhanden sind.
                        totalCost += Math.max(0, widgets.length - frameRects.length) * 0.75;
                        const averageCost = totalCost / frameRects.length;

                        if (!best || averageCost < best.cost) {
                            best = {
                                cost: averageCost,
                                containerId,
                                assignments
                            };
                        }
                    });
                });
            });

            if (best && best.assignments[currentIndex]) {
                const widgetId = best.assignments[currentIndex].widgetId;
                // Nur hinreichend plausible Matches akzeptieren. Bei einem guten
                // Grid-Match liegt der Wert typischerweise deutlich unter 1.
                if (best.cost < 3.5) {
                    return `widget-${widgetId}`;
                }
            }
        } catch (_) {
            // Sicherer Fallback weiter unten.
        }

        return 'widget-fallback';
    }

    function activateDetectedWidgetScope(scope) {
        if (!/^widget-\d+$/.test(String(scope || ''))) {
            return false;
        }

        widgetStorageScope = scope;
        widgetViewStorageKey =
            `symcon-energiefluss-${scope}-view`;
        widgetTechnicalLayoutStorageKey =
            `symcon-energiefluss-${scope}-technical-layout`;

        try {
            const storedView =
                window.localStorage.getItem(widgetViewStorageKey);

            const storedLayout =
                window.localStorage.getItem(
                    widgetTechnicalLayoutStorageKey
                );

            // Existiert für diese Kachel bereits ein eigener Zustand,
            // hat er Vorrang vor dem browserweiten Startwert.
            if (storedView === 'flow' || storedView === 'house') {
                currentDisplayMode = storedView;
            }

            if ([
                'compact',
                'compact-wide',
                'lite',
                'lite-wide',
                'full',
                'full-wide'
            ].includes(storedLayout)) {
                currentTechnicalLayout = storedLayout;
            }

            // Bei einer bislang unbekannten Kachel den bereits korrekt
            // geladenen Browserzustand als Ausgangswert übernehmen.
            if (storedView !== 'flow' && storedView !== 'house') {
                window.localStorage.setItem(
                    widgetViewStorageKey,
                    currentDisplayMode
                );
            }

            if (![
                'compact',
                'compact-wide',
                'lite',
                'lite-wide',
                'full',
                'full-wide'
            ].includes(storedLayout)) {
                window.localStorage.setItem(
                    widgetTechnicalLayoutStorageKey,
                    currentTechnicalLayout
                );
            }
        } catch (_) {
            // Browserweiter Zustand bleibt funktionsfähig.
        }

        widgetDetectionReady = true;
        updateTechnicalLayoutButtons();
        updateDisplayModeButton();

        if (lastStateData) {
            setState(lastStateData);
        }

        return true;
    }

    function startWidgetDetection() {
        /*
         * Bewusst deaktiviert:
         * Der Zustand wird überall ausschließlich über die Energiefluss-
         * Instanz-ID gespeichert. Keine Widget-/Grid-/Geometrie-Erkennung.
         */
        return;
    }

    function storeTechnicalLayout() {
        try {
            // Wie bei der Wärmepumpe immer sofort browserweit speichern.
            window.localStorage.setItem(
                TECHNICAL_LAYOUT_STORAGE_KEY,
                currentTechnicalLayout
            );

            // Nach erkannter Kachel zusätzlich kachelspezifisch speichern.
            if (widgetTechnicalLayoutStorageKey) {
                window.localStorage.setItem(
                    widgetTechnicalLayoutStorageKey,
                    currentTechnicalLayout
                );
            }
        } catch (error) {
            // LocalStorage ist optional.
        }
    }

    function applyTechnicalLayoutFromBrowser() {
        updateTechnicalLayoutButtons();

        if (lastStateData) {
            setState(lastStateData);
        } else {
            fit();
        }
    }

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
            currentDisplayMode = newMode;

            try {
                window.localStorage.setItem(
                    VIEW_STORAGE_KEY,
                    currentDisplayMode
                );

                if (widgetViewStorageKey) {
                    window.localStorage.setItem(
                        widgetViewStorageKey,
                        currentDisplayMode
                    );
                }
            } catch (error) {
                // LocalStorage ist optional.
            }

            applyDisplayMode(currentDisplayMode);

            // Die beiden Views haben unterschiedliche Zeichenflächen. Nach dem
            // lokalen Umschalten deshalb nur neu skalieren – ohne ApplyChanges
            // und ohne Änderung am Konfigurationsformular.
            if (lastStateData) {
                updateLayout(
                    Array.isArray(lastStateData.groups) ? lastStateData.groups.length : 0,
                    Array.isArray(lastStateData.pvs) ? lastStateData.pvs.length : 0,
                    Array.isArray(lastStateData.batteries) ? lastStateData.batteries.length : 0,
                    false,
                    currentDisplayMode,
                    !!lastStateData.hasWallbox
                );
            } else {
                fit();
            }
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
            currentTechnicalLayout =
                nextBase + (isWide ? '-wide' : '');
            storeTechnicalLayout();
            applyTechnicalLayoutFromBrowser();
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
            currentTechnicalLayout = isWide
                ? baseLayout
                : `${baseLayout}-wide`;
            storeTechnicalLayout();
            applyTechnicalLayoutFromBrowser();
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

    // Momentane Leistungswerte werden intern nur in ganzen Watt
    // weiterverarbeitet. Integer und Float bleiben als Eingabewerte erlaubt.
    // Math.trunc() schneidet Nachkommastellen zur Null hin ab:
    // 0.9 -> 0 W, -0.9 -> 0 W, 125.8 -> 125 W.
    function wholeWatts(value) {
        const numeric = Number(value);
        return Number.isFinite(numeric) ? Math.trunc(numeric) : 0;
    }

    function setState(d) {
        applyConfiguredColors(d);

        // Ausschließlich Leistungswerte in W normalisieren.
        // Energie, SOC, Spannung, Strom, Frequenz usw. bleiben unverändert.
        const grid = wholeWatts(d.grid);
        const imp = Math.max(grid, 0);

        const pvs = (d.pvs || []).map(pv => ({
            ...pv,
            value: wholeWatts(pv.value)
        }));

        const batteries = (d.batteries || []).map(bat => ({
            ...bat,
            value: wholeWatts(bat.value)
        }));

        const groups = (d.groups || []).map(group => ({
            ...group,
            value: wholeWatts(group.value)
        }));

        const wallboxSource =
            d.wallbox ||
            { name: 'Wallbox', value: 0, energy: '', socText: '', hasSoc: false };

        const wallbox = {
            ...wallboxSource,
            value: wholeWatts(wallboxSource.value)
        };

        const pvTotal = pvs.reduce((sum, pv) => sum + (pv.value || 0), 0);
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);

        // Netzbezug positiv, Rücklieferung negativ.
        const calculatedHouseBalance = Math.max(
            pvTotal + batteryTotal + grid,
            0
        );

        const inverterPower = wholeWatts(d.inverterPower);
        const calculatedHouseInverterGrid = Math.max(
            (Number.isFinite(inverterPower) ? inverterPower : 0) + grid,
            0
        );

        const houseCalculationMode = [
            'balance',
            'inverter-grid'
        ].includes(d.houseCalculationMode)
            ? d.houseCalculationMode
            : 'balance';

        let haus;

        // Eine verknüpfte Hausleistungsvariable hat immer Vorrang.
        if (
            d.available?.housePowerConfigured &&
            Number.isFinite(Number(d.housePower))
        ) {
            haus = Math.max(wholeWatts(d.housePower), 0);
        } else if (houseCalculationMode === 'inverter-grid') {
            // Wechselrichterleistung gesamt + Netzbezug − Netzeinspeisung.
            haus = calculatedHouseInverterGrid;
        } else {
            // PV + Batterieentladung − Batterieladung
            // + Netzbezug − Netzeinspeisung.
            haus = calculatedHouseBalance;
        }

        // Neue technische Ansicht.
        const powerState = {
            ...d,
            grid,
            inverterPower,
            housePower: wholeWatts(d.housePower),
            pvs,
            batteries,
            groups,
            wallbox
        };

        renderTechnicalView(
            powerState,
            grid,
            haus,
            pvs,
            batteries,
            wallbox,
            groups
        );

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
            powerState,
            grid,
            houseViewPower,
            pvs,
            batteries,
            wallbox
        );
        // Zustandsupdates ändern die lokal gewählte View nicht.
        applyDisplayMode(currentDisplayMode);

        updateLayout(
            groups.length,
            pvs.length,
            batteries.length,
            false,
            currentDisplayMode,
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

        // Wie bei der Wärmepumpe: den gespeicherten Browserzustand genau
        // einmal unmittelbar vor dem ersten Aufbau der Karte laden.
        initializeBrowserViewState();

        // Browseransicht sofort; Widget-Erkennung nur zusätzlich im Hintergrund.
        startWidgetDetection();

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
            [
                '__FLOW_DISPLAY__',
                '__HOUSE_DISPLAY__',
                '__POWER_FLOW_MODULE_URL__',
                '__SUNSYNK_MODULE_URL__',
            ],
            [
                $flowDisplay,
                $houseDisplay,
                htmlspecialchars($powerFlowModuleUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $sunsynkModuleUrl,
            ],
            $html
        );
    }

    private function GetVisualizationModuleDataUrl(string $asset, array $replacements = []): string
    {
        $path = __DIR__
            . DIRECTORY_SEPARATOR
            . 'assets'
            . DIRECTORY_SEPARATOR
            . 'vendor'
            . DIRECTORY_SEPARATOR
            . $asset;

        if (!is_file($path)) {
            throw new RuntimeException('Visualisierungsdatei fehlt im Modulbaum: ' . $asset);
        }

        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Visualisierungsdatei konnte nicht gelesen werden: ' . $asset);
        }

        // Falls ein Vendor-Modul die lokal mitgelieferte Lit-Datei relativ
        // referenziert, wird diese Referenz ebenfalls auf eine eingebettete
        // data:-Modul-URL umgebogen.
        foreach ($replacements as $relativeImport => $moduleUrl) {
            $source = str_replace(
                [
                    "'" . $relativeImport . "'",
                    '"' . $relativeImport . '"',
                ],
                [
                    "'" . $moduleUrl . "'",
                    '"' . $moduleUrl . '"',
                ],
                $source
            );
        }

        return 'data:text/javascript;base64,' . base64_encode($source);
    }

    private function ConfigureCalculatedVariables(): void
    {
        $this->ConfigureCalculatedVariable(
            'CreateVariableHousePower',
            'CalculatedHousePower',
            'Hausleistung',
            '~Watt',
            10
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableHouseEnergy',
            'CalculatedHouseEnergy',
            'Hausenergie heute',
            '~Electricity',
            20
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableAutarky',
            'CalculatedAutarky',
            'Autarkie',
            '~Intensity.100',
            30
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableSelfConsumption',
            'CalculatedSelfConsumption',
            'Eigenverbrauch',
            '~Intensity.100',
            40
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariablePvPower',
            'CalculatedPvPower',
            'PV-Gesamtleistung',
            '~Watt',
            50
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariablePvEnergy',
            'CalculatedPvEnergy',
            'PV-Tagesenergie',
            '~Electricity',
            60
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableInverterPower',
            'CalculatedInverterPower',
            'Inverterleistung gesamt',
            '~Watt',
            65
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableGridImportPower',
            'CalculatedGridImportPower',
            'Netzbezug',
            '~Watt',
            70
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableGridExportPower',
            'CalculatedGridExportPower',
            'Netzeinspeisung',
            '~Watt',
            80
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableBatteryPower',
            'CalculatedBatteryPower',
            'Batterieleistung gesamt',
            '~Watt',
            90
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableBatteryChargeEnergy',
            'CalculatedBatteryChargeEnergy',
            'Batterie-Ladeenergie heute',
            '~Electricity',
            100
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableBatteryDischargeEnergy',
            'CalculatedBatteryDischargeEnergy',
            'Batterie-Entladeenergie heute',
            '~Electricity',
            110
        );
        $this->ConfigureCalculatedStringVariable(
            'CreateVariableBatteryRuntime',
            'CalculatedBatteryRuntime',
            'Batterie-Laufzeit',
            115
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableWallboxPower',
            'CalculatedWallboxPower',
            'Wallbox-Leistung',
            '~Watt',
            120
        );
        $this->ConfigureCalculatedVariable(
            'CreateVariableWallboxEnergy',
            'CalculatedWallboxEnergy',
            'Wallbox-Energie heute',
            '~Electricity',
            130
        );
    }

    private function ConfigureCalculatedVariable(
        string $property,
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {
        if ($this->ReadPropertyBoolean($property)) {
            $this->RegisterVariableFloat(
                $ident,
                $name,
                $profile,
                $position
            );
            return;
        }

        $variableID = @$this->GetIDForIdent($ident);
        if (
            is_int($variableID) &&
            $variableID > 0 &&
            IPS_VariableExists($variableID)
        ) {
            $this->UnregisterVariable($ident);
        }
    }

    private function ConfigureCalculatedStringVariable(
        string $property,
        string $ident,
        string $name,
        int $position
    ): void {
        if ($this->ReadPropertyBoolean($property)) {
            $variableID = @$this->GetIDForIdent($ident);

            if (
                !is_int($variableID) ||
                $variableID <= 0 ||
                !IPS_VariableExists($variableID)
            ) {
                $this->RegisterVariableString(
                    $ident,
                    $name,
                    '',
                    $position
                );
            }

            return;
        }

        $variableID = @$this->GetIDForIdent($ident);
        if (
            is_int($variableID) &&
            $variableID > 0 &&
            IPS_VariableExists($variableID)
        ) {
            $this->UnregisterVariable($ident);
        }
    }

    private function FormatBatteryRuntime(float $hours): string
    {
        if (!is_finite($hours) || $hours < 0.0) {
            return '--';
        }

        $totalMinutes = (int) round($hours * 60.0);
        $days = intdiv($totalMinutes, 1440);
        $remaining = $totalMinutes % 1440;
        $wholeHours = intdiv($remaining, 60);
        $minutes = $remaining % 60;

        if ($days > 0) {
            return sprintf(
                '%d d %d h %d min',
                $days,
                $wholeHours,
                $minutes
            );
        }

        if ($wholeHours > 0) {
            return sprintf(
                '%d h %d min',
                $wholeHours,
                $minutes
            );
        }

        return sprintf('%d min', $minutes);
    }

    private function CalculateBatteryRuntimeString(array $batteries): string
    {
        if ($batteries === []) {
            return '--';
        }

        $totalPower = 0.0;
        $availableDischargeEnergy = 0.0;
        $missingChargeEnergy = 0.0;

        $totalCapacity = 0.0;
        $weightedSoc = 0.0;
        $weightedMinSoc = 0.0;

        foreach ($batteries as $battery) {
            if (!is_array($battery)) {
                continue;
            }

            $capacity = max(
                (float) ($battery['capacityKWh'] ?? 0.0),
                0.0
            );
            $soc = min(
                max((float) ($battery['soc'] ?? 0.0), 0.0),
                100.0
            );
            $minSoc = min(
                max(
                    (float) (
                        $battery['maxDischargeSoc'] ?? 0.0
                    ),
                    0.0
                ),
                100.0
            );
            $power = (float) ($battery['value'] ?? 0.0);

            if ($capacity <= 0.0) {
                continue;
            }

            $totalCapacity += $capacity;
            $weightedSoc += $capacity * $soc;
            $weightedMinSoc += $capacity * $minSoc;
            $totalPower += $power;

            $availableDischargeEnergy +=
                $capacity
                * max($soc - $minSoc, 0.0)
                / 100.0;

            $missingChargeEnergy +=
                $capacity
                * max(100.0 - $soc, 0.0)
                / 100.0;
        }

        if ($totalCapacity <= 0.0) {
            return '--';
        }

        $currentSoc = (int) round(
            $weightedSoc / $totalCapacity
        );
        $dischargeLimit = (int) round(
            $weightedMinSoc / $totalCapacity
        );

        // Positive Batterieleistung = Entladen zum Haus.
        if ($totalPower > 1.0) {
            $hours = $availableDischargeEnergy
                / ($totalPower / 1000.0);

            $formatted = $this->FormatBatteryRuntime($hours);

            if ($formatted === '--') {
                return '--';
            }

            return sprintf(
                '%s (%d %% → %d %%)',
                $formatted,
                $currentSoc,
                $dischargeLimit
            );
        }

        // Negative Batterieleistung = Laden.
        if ($totalPower < -1.0) {
            $hours = $missingChargeEnergy
                / (abs($totalPower) / 1000.0);

            $formatted = $this->FormatBatteryRuntime($hours);

            if ($formatted === '--') {
                return '--';
            }

            return sprintf(
                '%s (%d %% → 100 %%)',
                $formatted,
                $currentSoc
            );
        }

        return sprintf(
            'Haltend (%d %%)',
            $currentSoc
        );
    }

    private function UpdateCalculatedVariables(array $payload): void
    {
        $pvs = is_array($payload['pvs'] ?? null)
            ? $payload['pvs']
            : [];
        $batteries = is_array($payload['batteries'] ?? null)
            ? $payload['batteries']
            : [];

        $pvPower = array_reduce(
            $pvs,
            static fn(float $sum, array $pv): float =>
                $sum + max((float) ($pv['value'] ?? 0.0), 0.0),
            0.0
        );

        $batteryPower = array_reduce(
            $batteries,
            static fn(float $sum, array $battery): float =>
                $sum + (float) ($battery['value'] ?? 0.0),
            0.0
        );

        $batteryChargeEnergy = array_reduce(
            $batteries,
            static fn(float $sum, array $battery): float =>
                $sum + max((float) ($battery['chargeEnergy'] ?? 0.0), 0.0),
            0.0
        );

        $batteryDischargeEnergy = array_reduce(
            $batteries,
            static fn(float $sum, array $battery): float =>
                $sum + max((float) ($battery['dischargeEnergy'] ?? 0.0), 0.0),
            0.0
        );

        $grid = (float) ($payload['grid'] ?? 0.0);
        $gridImportPower = max($grid, 0.0);
        $gridExportPower = max(-$grid, 0.0);

        $calculatedHouseBalance = max(
            $pvPower + $batteryPower + $grid,
            0.0
        );
        $calculatedHouseInverterGrid = max(
            (float) ($payload['inverterPower'] ?? 0.0) + $grid,
            0.0
        );

        $houseMode = (string) (
            $payload['houseCalculationMode'] ?? 'balance'
        );

        $housePowerConfigured = (bool) (
            $payload['available']['housePowerConfigured'] ?? false
        );

        if ($housePowerConfigured) {
            $housePower = max(
                (float) ($payload['housePower'] ?? 0.0),
                0.0
            );
        } elseif ($houseMode === 'inverter-grid') {
            $housePower = $calculatedHouseInverterGrid;
        } else {
            $housePower = $calculatedHouseBalance;
        }

        $houseEnergy = max(
            (float) ($payload['houseEnergy'] ?? 0.0),
            0.0
        );
        $pvEnergy = max(
            (float) ($payload['inverterDailyEnergy'] ?? 0.0),
            0.0
        );
        $gridImportEnergy = max(
            (float) ($payload['gridImportEnergyValue'] ?? 0.0),
            0.0
        );

        $autarky = $houseEnergy > 0.0
            ? (($houseEnergy - $gridImportEnergy) / $houseEnergy) * 100.0
            : 0.0;

        $selfSuppliedHouseEnergy = max(
            $houseEnergy - $gridImportEnergy,
            0.0
        );

        $ownDeliveredEnergy = max(
            $pvEnergy
            + $batteryDischargeEnergy
            - $batteryChargeEnergy,
            0.0
        );

        $selfConsumption = $ownDeliveredEnergy > 0.0
            ? (
                $selfSuppliedHouseEnergy /
                $ownDeliveredEnergy
            ) * 100.0
            : 0.0;

        if ((bool) ($payload['autarkyVariableAvailable'] ?? false)) {
            $autarky = (float) (
                $payload['autarkyVariableValue'] ?? 0.0
            );
        }

        if ((bool) (
            $payload['selfConsumptionVariableAvailable'] ?? false
        )) {
            $selfConsumption = (float) (
                $payload['selfConsumptionVariableValue'] ?? 0.0
            );
        }

        $autarky = min(max($autarky, 0.0), 100.0);
        $selfConsumption = min(
            max($selfConsumption, 0.0),
            100.0
        );

        $wallbox = is_array($payload['wallbox'] ?? null)
            ? $payload['wallbox']
            : [];

        // Leistungswerte werden im Modul immer in Watt geführt und deshalb
        // auf ganze Watt gerundet. Energie- und Prozentwerte bleiben Float.
        $values = [
            'CalculatedHousePower' => [
                'value' => round($housePower),
                'tolerance' => 0.0,
            ],
            'CalculatedHouseEnergy' => [
                'value' => $houseEnergy,
                'tolerance' => 0.0001,
            ],
            'CalculatedAutarky' => [
                'value' => $autarky,
                'tolerance' => 0.0001,
            ],
            'CalculatedSelfConsumption' => [
                'value' => $selfConsumption,
                'tolerance' => 0.0001,
            ],
            'CalculatedPvPower' => [
                'value' => round($pvPower),
                'tolerance' => 0.0,
            ],
            'CalculatedPvEnergy' => [
                'value' => $pvEnergy,
                'tolerance' => 0.0001,
            ],
            'CalculatedInverterPower' => [
                'value' => round(
                    (float) ($payload['inverterPower'] ?? 0.0)
                ),
                'tolerance' => 0.0,
            ],
            'CalculatedGridImportPower' => [
                'value' => round($gridImportPower),
                'tolerance' => 0.0,
            ],
            'CalculatedGridExportPower' => [
                'value' => round($gridExportPower),
                'tolerance' => 0.0,
            ],
            'CalculatedBatteryPower' => [
                'value' => round($batteryPower),
                'tolerance' => 0.0,
            ],
            'CalculatedBatteryChargeEnergy' => [
                'value' => $batteryChargeEnergy,
                'tolerance' => 0.0001,
            ],
            'CalculatedBatteryDischargeEnergy' => [
                'value' => $batteryDischargeEnergy,
                'tolerance' => 0.0001,
            ],
            'CalculatedWallboxPower' => [
                'value' => round(
                    max((float) ($wallbox['value'] ?? 0.0), 0.0)
                ),
                'tolerance' => 0.0,
            ],
            'CalculatedWallboxEnergy' => [
                'value' => max(
                    (float) ($wallbox['energyValue'] ?? 0.0),
                    0.0
                ),
                'tolerance' => 0.0001,
            ],
        ];

        foreach ($values as $ident => $definition) {
            $variableID = @$this->GetIDForIdent($ident);
            if (
                !is_int($variableID) ||
                $variableID <= 0 ||
                !IPS_VariableExists($variableID)
            ) {
                continue;
            }

            $newValue = (float) $definition['value'];
            $tolerance = (float) $definition['tolerance'];
            $currentValue = (float) GetValue($variableID);

            // Nur bei einer tatsächlichen Wertänderung schreiben.
            // Leistungswerte sind ganze Watt und haben daher Toleranz 0.
            // Bei Float-Werten verhindert eine kleine Toleranz unnötige
            // Aktualisierungen durch Fließkommaabweichungen.
            if (abs($currentValue - $newValue) > $tolerance) {
                $this->SetValue($ident, $newValue);
            }
        }

        $runtimeVariableID = @$this->GetIDForIdent(
            'CalculatedBatteryRuntime'
        );
        if (
            is_int($runtimeVariableID) &&
            $runtimeVariableID > 0 &&
            IPS_VariableExists($runtimeVariableID)
        ) {
            $runtime = $this->CalculateBatteryRuntimeString(
                $batteries
            );
            $currentRuntime = (string) GetValue(
                $runtimeVariableID
            );

            // String nur aktualisieren, wenn sich der Text ändert.
            if ($currentRuntime !== $runtime) {
                $this->SetValue(
                    'CalculatedBatteryRuntime',
                    $runtime
                );
            }
        }
    }

    private function PushState(): void
    {
        $payload = $this->BuildPayload();

        $this->UpdateCalculatedVariables($payload);

        $this->UpdateVisualizationValue(
            json_encode(
                $payload,
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

        // Die Auswahl HouseCalculationMode gilt ausschließlich für die
        // momentane Hausleistung in Watt.
        //
        // Für die Tagesenergie gilt:
        // 1. Ist eine gültige HouseEnergy-Variable gewählt, wird deren Wert
        //    direkt verwendet.
        // 2. Andernfalls erfolgt die interne Bilanz:
        //    PV + Netzbezug - Einspeisung
        //    + Batterieentladung - Batterieladung.
        $houseEnergy = 0.0;
        $houseEnergyAvailable = false;
        $houseCalculationMode = $this->ReadPropertyString(
            'HouseCalculationMode'
        );

        $houseEnergyID = $this->ReadPropertyInteger('HouseEnergy');
        $hasConfiguredHouseEnergy =
            $houseEnergyID > 0 &&
            IPS_VariableExists($houseEnergyID);

        $balanceEnergyAvailable =
            $hasPvEnergy &&
            $hasGridImportEnergy &&
            $hasGridExportEnergy &&
            $hasBatteryEnergy;

        if ($hasConfiguredHouseEnergy) {
            $houseEnergy = max(
                0.0,
                (float) GetValue($houseEnergyID)
            );
            $houseEnergyAvailable = true;
        } elseif ($balanceEnergyAvailable) {
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