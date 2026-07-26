<?php

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

        $this->RegisterPropertyInteger('SolarFlowPV', 0);
        $this->RegisterPropertyInteger('HoymilesPV', 0);
        $this->RegisterPropertyInteger('BatteryOut', 0);
        $this->RegisterPropertyInteger('BatterySoC', 0);
        $this->RegisterPropertyInteger('L1', 0);
        $this->RegisterPropertyInteger('L2', 0);
        $this->RegisterPropertyInteger('L3', 0);
        $this->RegisterPropertyInteger('SettingsCategory', 0);
        $this->RegisterPropertyString('Groups', '[]');
        $this->RegisterPropertyInteger('DayProduction', 0);
        $this->RegisterPropertyInteger('WeekProduction', 0);
        $this->RegisterPropertyInteger('DayGridImport', 0);
        $this->RegisterPropertyInteger('WeekGridImport', 0);

        // HTML-SDK als Darstellung aktivieren
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Defensiv: Fehler hier dürfen niemals den Symcon-Start blockieren
        try {
            // Alte Nachrichten-Registrierungen entfernen
            foreach ($this->GetMessageList() as $senderID => $messages) {
                foreach ($messages as $message) {
                    if ($message === VM_UPDATE) {
                        $this->UnregisterMessage($senderID, VM_UPDATE);
                    }
                }
            }

            // Alte Referenzen entfernen
            foreach ($this->GetReferenceList() as $referenceID) {
                $this->UnregisterReference($referenceID);
            }

            // Für alle konfigurierten Variablen auf Änderungen lauschen + referenzieren
            foreach ($this->CollectVariableIDs() as $id) {
                if ($id > 0 && IPS_VariableExists($id)) {
                    $this->RegisterMessage($id, VM_UPDATE);
                    $this->RegisterReference($id);
                }
            }

            // Aktuellen Stand an offene Kacheln pushen
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
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Erzeuger & Batterie',
                    'items'   => [
                        ['type' => 'SelectVariable', 'name' => 'SolarFlowPV', 'caption' => 'SolarFlow PV-Leistung (W)'],
                        ['type' => 'SelectVariable', 'name' => 'HoymilesPV', 'caption' => 'Hoymiles PV-Leistung (W)'],
                        ['type' => 'SelectVariable', 'name' => 'BatteryOut', 'caption' => 'Batterie-Ausgang ins Haus (W)'],
                        ['type' => 'SelectVariable', 'name' => 'BatterySoC', 'caption' => 'Batterie-Ladezustand (%)'],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Netz (Shelly Pro3EM)',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Netzbezug gesamt wird aus L1 + L2 + L3 berechnet (vorzeichenrichtig). L3 ist oft negativ (Batterie-Einspeisung).',
                        ],
                        ['type' => 'SelectVariable', 'name' => 'L1', 'caption' => 'L1 Leistung (W)'],
                        ['type' => 'SelectVariable', 'name' => 'L2', 'caption' => 'L2 Leistung (W)'],
                        ['type' => 'SelectVariable', 'name' => 'L3', 'caption' => 'L3 Leistung (W)'],
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
                            'caption' => 'Zählervariablen für die Statistik-Anzeige rechts. Die Einheit kommt aus dem Variablenprofil (z. B. kWh).',
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
                            'caption' => 'Kategorie \'SolarFlow Einstellungen\' auswählen, die das PID-Skript anlegt. Dann werden die Parameter unter der Grafik angezeigt und sind editierbar.',
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
                    'caption' => 'Werte jetzt aktualisieren',
                    'onClick' => 'SFV_Refresh($id);',
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

    // Von der Kachel (JavaScript requestAction) aufgerufen
    public function RequestAction(string $Ident, mixed $Value): void
    {
        // Konfig-Felder: "Cfg<FeldID>" -> Einstellungs-Variable schreiben
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

    // Button im Konfigurationsformular
    public function Refresh(): void
    {
        $this->PushState();
    }

    public function GetVisualizationTile(): string
    {
        try {
            $html = file_get_contents(__DIR__ . '/module.html');
            if ($html === false) {
                throw new RuntimeException('module.html konnte nicht gelesen werden.');
            }

            $payload = json_encode(
                $this->BuildPayload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            // handleMessage() ist in module.html definiert; hier initial mit aktuellen Werten aufrufen
            return $html . '<script>handleMessage(' . $payload . ');</script>';
        } catch (Throwable $e) {
            return '<div style="padding:1em">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
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

    private function CollectVariableIDs(): array
    {
        $ids = [];

        foreach ([
            'SolarFlowPV',
            'HoymilesPV',
            'BatteryOut',
            'BatterySoC',
            'L1',
            'L2',
            'L3',
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
        $l1 = $this->ReadVar('L1');
        $l2 = $this->ReadVar('L2');
        $l3 = $this->ReadVar('L3');
        $grid = $l1 + $l2 + $l3;

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

        // Statistik: formatierte Werte (Einheit aus dem Variablenprofil)
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
            'solarflow' => $this->ReadVar('SolarFlowPV'),
            'hoymiles'  => $this->ReadVar('HoymilesPV'),
            'battOut'   => $this->ReadVar('BatteryOut'),
            'soc'       => $this->ReadVar('BatterySoC'),
            'l1'        => $l1,
            'l2'        => $l2,
            'l3'        => $l3,
            'grid'      => $grid,
            'groups'    => $groups,
            'stats'     => $stats,
            'hasConfig' => $config !== null,
            'config'    => $config ?? (object) [],
        ];
    }
}
