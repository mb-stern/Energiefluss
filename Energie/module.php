<?php

// SolarFlowTile v1.6 (Build 7)

declare(strict_types=1);

class SolarFlowTile extends IPSModule
{
    // Idents der Einstellungs-Variablen, die das PID-Skript in der Kategorie
    // "SolarFlow Einstellungen" anlegt. Schlüssel = Feld-ID in der Kachel.
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

    public function Create()
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

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Defensiv: Fehler hier dürfen niemals den Symcon-Start blockieren
        try {
            // Alte Nachrichten-Registrierungen entfernen
            foreach ($this->GetMessageList() as $senderID => $messages) {
                foreach ($messages as $message) {
                    if ($message == VM_UPDATE) {
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
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->PushState();
            }
        } catch (Throwable $e) {
            $this->LogMessage('ApplyChanges: ' . $e->getMessage(), KL_ERROR);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        try {
            if ($Message == VM_UPDATE && IPS_GetKernelRunlevel() == KR_READY) {
                $this->PushState();
            }
        } catch (Throwable $e) {
            $this->LogMessage('MessageSink: ' . $e->getMessage(), KL_ERROR);
        }
    }

    // Von der Kachel (JavaScript requestAction) aufgerufen
    public function RequestAction($Ident, $Value)
    {
        // Konfig-Felder: "Cfg<FeldID>" -> Einstellungs-Variable schreiben
        if (strpos($Ident, 'Cfg') === 0) {
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
            if ($type == VARIABLETYPE_STRING) {
                SetValue($varID, (string) $Value);
            } elseif ($type == VARIABLETYPE_INTEGER) {
                SetValue($varID, (int) round(floatval($Value)));
            } else {
                SetValue($varID, floatval($Value));
            }
            $this->PushState();
            return;
        }
    }

    // Button im Konfigurationsformular
    public function Refresh()
    {
        $this->PushState();
    }

    public function GetVisualizationTile()
    {
        try {
            $html = file_get_contents(__DIR__ . '/module.html');
            $payload = json_encode($this->BuildPayload());
            // handleMessage() ist in module.html definiert; hier initial mit aktuellen Werten aufrufen
            return $html . '<script>handleMessage(' . $payload . ');</script>';
        } catch (Throwable $e) {
            return '<div style="padding:1em">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    private function PushState()
    {
        $this->UpdateVisualizationValue(json_encode($this->BuildPayload()));
    }

    private function ReadVar(string $property): float
    {
        $id = $this->ReadPropertyInteger($property);
        if ($id > 0 && IPS_VariableExists($id)) {
            return floatval(GetValue($id));
        }
        return 0.0;
    }

    private function CollectVariableIDs(): array
    {
        $ids = [];
        foreach (['SolarFlowPV', 'HoymilesPV', 'BatteryOut', 'BatterySoC', 'L1', 'L2', 'L3', 'DayProduction', 'WeekProduction', 'DayGridImport', 'WeekGridImport'] as $p) {
            $id = $this->ReadPropertyInteger($p);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $groups = json_decode($this->ReadPropertyString('Groups'), true);
        if (is_array($groups)) {
            foreach ($groups as $g) {
                $vid = intval($g['VariableID'] ?? 0);
                if ($vid > 0) {
                    $ids[] = $vid;
                }
                $did = intval($g['DailyVariableID'] ?? 0);
                if ($did > 0) {
                    $ids[] = $did;
                }
            }
        }
        $catID = $this->ReadPropertyInteger('SettingsCategory');
        if ($catID > 0 && IPS_ObjectExists($catID)) {
            foreach (self::CONFIG_MAP as $ident) {
                $vid = @IPS_GetObjectIDByIdent($ident, $catID);
                if ($vid !== false) {
                    $ids[] = $vid;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    private function BuildConfig()
    {
        $catID = $this->ReadPropertyInteger('SettingsCategory');
        if ($catID <= 0 || !IPS_ObjectExists($catID)) {
            return null;
        }
        $out = [];
        foreach (self::CONFIG_MAP as $field => $ident) {
            $vid = @IPS_GetObjectIDByIdent($ident, $catID);
            if ($vid !== false && IPS_VariableExists($vid)) {
                $out[$field] = GetValue($vid);
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
            foreach ($decoded as $g) {
                $vid = intval($g['VariableID'] ?? 0);
                $val = ($vid > 0 && IPS_VariableExists($vid)) ? floatval(GetValue($vid)) : 0.0;
                $did = intval($g['DailyVariableID'] ?? 0);
                $daily = ($did > 0 && IPS_VariableExists($did)) ? GetValueFormatted($did) : '';
                $groups[] = [
                    'name'  => (string) ($g['Name'] ?? ''),
                    'icon'  => (string) ($g['Icon'] ?? 'plug'),
                    'value' => $val,
                    'daily' => $daily,
                ];
            }
        }

        $config = $this->BuildConfig();

        // Statistik: formatierte Werte (Einheit aus dem Variablenprofil)
        $stats = [];
        $statDefs = [
            ['DayProduction',  'Tagesproduktion'],
            ['WeekProduction', 'Wochenproduktion'],
            ['DayGridImport',  'Netzbezug heute'],
            ['WeekGridImport', 'Netzbezug Woche'],
        ];
        foreach ($statDefs as $def) {
            $sid = $this->ReadPropertyInteger($def[0]);
            if ($sid > 0 && IPS_VariableExists($sid)) {
                $stats[] = [
                    'label' => $def[1],
                    'value' => GetValueFormatted($sid),
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
