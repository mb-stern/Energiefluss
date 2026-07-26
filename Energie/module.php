ChatGPT Plus




heute 12:11

Pasted code.html
Datei
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


Pasted code(56).php
Datei
ich kann das modul nichte rtellen woe ist der fehelr


Pasted code(57).php
Datei

Pasted code (2).html
Datei
bitte das html als Funktion ins moduel.php intgerieren, dann legen wir mit en modigfikatinen los


Eingefügter Text(69).txt
Dokument
Das hat nicht funktioniert  da ist die ahsusnasicht leer nur das Umschaltmenu ist vorhanden bitte nun auch SVG einpflegen

so was sähe auch echt cool aus

Generiertes Bild: Energieflussübersicht für Smart Home


Bearbeiten


Generiertes Bild: Energieflussübersicht für Smart Homes


Bearbeiten



Überlegt, wie Bilder integriert werden können

Generiertes Bild: Energieübersicht für modernes Zuhause


Bearbeiten



Überlegte die Bildintegration
Ich setze es jetzt wirklich als Grafikhintergrund um: Haus, PV-Dach, Batterie, Wallbox, Auto und Netz bleiben als hochwertige Grafik erhalten; alle Texte, Werte, Karten und farbigen Flusslinien kommen dynamisch aus der module.php darüber. So sieht es wie dein Entwurf aus, ohne dass feste Beispielwerte im Bild stehen.


Ich brauche dafür noch einen sauberen Hintergrund ohne feste Beispielwerte und ohne fest eingezeichnete Energieflüsse; sonst würden sich die statischen Werte mit deinen Live-Daten überlagern. Ich bereinige deshalb genau die Hausgrafik und bette sie anschließend direkt in die module.php ein.


Generiertes Bild: Energiefluss-Übersicht des Hauses


Bearbeiten


vermutlich bruachen wir doch eien bilddatei oder?


Eingefügter Text(70).txt
Dokument
was soll das jetzt mit deiner Module.phph voreher so und jetz gehst du augf eien uralte?

Generiertes Bild: Moderne Energieübersicht bei Nacht


Bearbeiten



Überprüft Dateien und Pfade
Ich mache jetzt zuerst die saubere Hintergrundgrafik in groß – nur Haus, PV, Wechselrichter, Batterie, Wallbox, Auto, Netz und Umgebung. Keine Karten, keine Werte, keine Flusslinien. Danach ersetze ich in deiner aktuell hochgeladenen module.php ausschließlich das eingebettete Bild durch house.png und lasse die dynamischen SVG-Linien darüber laufen.

Das Bild ist groß genug, aber es hat wieder feste Karten/Werte erzeugt – genau das dürfen wir nicht haben. Ich entferne diese jetzt aus dieser Grafik und behalte nur Haus, PV, Wechselrichter, Batterie, Wallbox, Auto, Netz und Umgebung.


Generiertes Bild: Moderne Solarenergie im Vorort design


Bearbeiten

Die saubere Hintergrundgrafik ist jetzt erstellt: ohne feste Werte, Karten und Energieflusslinien. Für die module.php brauche ich jetzt noch einmal deine aktuelle PHP-Datei als Upload, damit ich garantiert nicht wieder auf einen älteren Stand zurückgreife.


Generiertes Bild: Moderne umweltfreundliche Architektur im Alltag


Bearbeiten



Eingefügter Text(71).txt
Dokument

gibt es eigentlich nichts vorgefertigtes im netz wo svg bereits drüber liegt. Wei ljetz sieht es so asu svg am falschen Ort etc

Das sieht doch nicht im entferntesten so asu


Eingefügter Text(72).txt
Dokument
ist eas daswas du integriern wolltet

Erstens: Netz muss nur bis zum SMartmeter. Zweitetens: Hausverbrauch mussins Hsu nicht ins Dach. Drittens: Wenn eine zweite Solaranlage konfiguriert ist kann diejemige auf dem Caraport genommen werden, jetz ist nur diejenige im Haus aktiv. Viertens. Wenn keine Wallbox aktiviert ist, dann dieses Bild ausblenden. Fünftens. Die Leitung von der PVA auf dem Dach muss nicht rings um das Dach, sondern könnt direkter. Oder ist da irgendwas schon vogegebn das ds so eongerichtet ist

Generiertes Bild: Energiefluss im Smart Home Dashboard


Bearbeiten


Generiertes Bild: Energieflussübersicht für modernes Zuhause


Bearbeiten



Eingefügter Text(73).txt
Dokument
Geh von dem aus, as andre funktioniert ja bestimmt niocht

0ebc6e2d-aa12-4a51-a44f-cb242d3b50cc.png
Mach die Linien nicht so krumm. VOrher war der Netzeingen besser, er ging in den Boden. Wallbox ist ok. Soalranlage Carport bitte die LEitung englang der Wallbox, Leitung legen und dann gleich unterhalb der Soalranlge beenden. Das gleiche bie der SOlaralnag dach, nicht schräg sondern gersde bis untern in die Panels führen

Generiertes Bild: Modernes Haus mit Energiesystem-Übersicht


Bearbeiten



Eingefügter Text (2)(3).txt
Dokument
Du sollst nicht ein bild erseleln sondern da im code anpassen


Analysiert
from pathlib import Path
import subprocess

src = Path("/mnt/data/Eingefügter Text (2)(3).txt")
code = src.read_text(encoding="utf-8")

# ------------------------------------------------------------------
# 1) Statische Grundkabel der Hausansicht geometrisch bereinigen
# ------------------------------------------------------------------
replacements = {
    # PV Hausdach: komplett gerade/vertikal in die Panels.
    '<path id="sig-cable-pv1" class="sig-cable" d="M 610 175 L 610 330 L 505 560"></path>':
    '<path id="sig-cable-pv1" class="sig-cable" d="M 505 560 L 505 175"></path>',

    # PV Carport: vom zentralen Bereich erst waagrecht Richtung Wallbox,
    # dann senkrecht nach oben bis direkt unter die Carport-Panels.
    '<path id="sig-cable-pv2" class="sig-cable" d="M 185 355 L 300 420 L 410 500 L 505 560" style="display:none"></path>':
    '<path id="sig-cable-pv2" class="sig-cable" d="M 505 560 L 350 560 L 350 535 L 185 535 L 185 355" style="display:none"></path>',

    # Hausverbrauch: nur rechtwinklige Segmente, Ziel klar im Haus.
    '<path id="sig-cable-home" class="sig-cable" d="M 535 570 L 650 520 L 755 455"></path>':
    '<path id="sig-cable-home" class="sig-cable" d="M 535 570 L 680 570 L 680 455 L 755 455"></path>',

    # Netz: wieder mit senkrechtem Stück in den Boden.
    '<path id="sig-cable-grid" class="sig-cable" d="M 600 645 L 740 695 L 790 695"></path>':
    '<path id="sig-cable-grid" class="sig-cable" d="M 600 645 L 790 645 L 790 695 L 855 695 L 855 830"></path>',
}

for old, new in replacements.items():
    if old not in code:
        raise RuntimeError(f"Grundkabel nicht gefunden:\n{old}")
    code = code.replace(old, new, 1)

# ------------------------------------------------------------------
# 2) Dynamische farbige Pfade exakt auf dieselben Linien legen
# ------------------------------------------------------------------
dynamic_replacements = {
    # PV1
    "'M 610 175 L 610 330 L 505 560',":
    "'M 505 560 L 505 175',",

    # PV2
    "'M 185 355 L 300 420 L 410 500 L 505 560',":
    "'M 505 560 L 350 560 L 350 535 L 185 535 L 185 355',",

    # Haus
    "'M 535 570 L 650 520 L 755 455',":
    "'M 535 570 L 680 570 L 680 455 L 755 455',",

    # Netz
    "'M 600 645 L 740 695 L 790 695',":
    "'M 600 645 L 790 645 L 790 695 L 855 695 L 855 830',",
}

for old, new in dynamic_replacements.items():
    if old not in code:
        raise RuntimeError(f"Dynamischer Pfad nicht gefunden: {old}")
    code = code.replace(old, new, 1)

# ------------------------------------------------------------------
# 3) Kommentare im Code passend aktualisieren
# ------------------------------------------------------------------
code = code.replace(
    "// PV 1: Hausdach -> zentraler SigenStor-Knoten.\n"
    "        // Bewusst DIREKT, nicht mehr entlang/rings um das Dach.",
    "// PV 1: vom zentralen Knoten senkrecht nach oben bis in die Dach-Panels."
)

code = code.replace(
    "// PV 2: Carport -> derselbe zentrale Knoten.",
    "// PV 2: vom zentralen Bereich entlang des Wallbox-Bereichs und dann senkrecht\n"
    "        // nach oben bis direkt unter die Carport-Panels."
)

code = code.replace(
    "// Hausverbrauch: vom SigenStor DIREKT ins Haus.",
    "// Hausverbrauch: rechtwinklig vom zentralen Knoten direkt ins Haus."
)

code = code.replace(
    "// Netz: nur bis zum Smartmeter. Der Pfad endet deshalb bereits bei 790/695.",
    "// Netz: rechtwinklig zum Smartmeter und von dort senkrecht in den Boden."
)

# ------------------------------------------------------------------
# 4) Ausgabe + PHP Syntaxprüfung
# ------------------------------------------------------------------
out = Path("/mnt/data/module_sigenergy_linien_gerade.php")
out.write_text(code, encoding="utf-8")

check = subprocess.run(
    ["php", "-l", str(out)],
    capture_output=True,
    text=True
)

if check.returncode != 0:
    raise RuntimeError(check.stdout + check.stderr)

print(check.stdout.strip())
print(out)
STDOUT/STDERR
No syntax errors detected in /mnt/data/module_sigenergy_linien_gerade.php
/mnt/data/module_sigenergy_linien_gerade.php
Erledigt — diesmal wirklich nur im Code deiner aktuellen Version. 


Geändert habe ich:

Dach-PV: jetzt senkrecht gerade von unten in die Panels

Carport-PV: erst entlang des unteren/Wallbox-Bereichs, dann senkrecht nach oben bis unter die Carport-Panels

Hausverbrauch: nur noch rechtwinklige Segmente

Netz: wieder sauber zum Smartmeter und danach senkrecht in den Boden

Batterie unverändert

Wallbox unverändert

Auch die grauen Grundlinien und die farbigen animierten Linien nutzen jetzt jeweils denselben Verlauf.

PHP-Syntax ist geprüft: keine Fehler.

module_sigenergy_linien_gerade.php 


e3d24c38-508c-4d2d-a40a-2f815d2d5fe9.png
Nur einen Winkel im Netzkabel und direkt ind en Boiden, nicht ins fenster eine. Die LEitung zum Caraport PVA entlang der LEitung wlallbox. Und die Leitung PVA  Dach bis zum anfang der Panels. UNd berücksichtige bitte das Smartmeter, unterssen get das einfach hindurch Ich zeig dir wie es saussiht du musst kein bild ersteleln


Analysiert
from pathlib import Path
import subprocess

src = Path("/mnt/data/module_sigenergy_linien_gerade.php")
if not src.exists():
    raise FileNotFoundError(src)

code = src.read_text(encoding="utf-8")

# ------------------------------------------------------------------
# Gewünschte Geometrie:
# - Netz: zentraler Knoten -> Smartmeter -> senkrecht Boden (1 Winkel)
# - PV Carport: entlang Wallbox-Trasse, dann senkrecht zu den Panels
# - PV Dach: senkrecht bis zum unteren Panelrand
# ------------------------------------------------------------------

# 1) Statische graue Grundpfade
static_replacements = {
    # Dach-PV endet am unteren Beginn der Panels, statt weit in die Module hinein.
    '<path id="sig-cable-pv1" class="sig-cable" d="M 505 560 L 505 175"></path>':
    '<path id="sig-cable-pv1" class="sig-cable" d="M 505 560 L 505 245"></path>',

    # Carport-PV benutzt unten die gleiche Trasse wie die Wallbox,
    # zweigt links ab und geht erst dort senkrecht zu den Carport-Panels hoch.
    '<path id="sig-cable-pv2" class="sig-cable" d="M 505 560 L 350 560 L 350 535 L 185 535 L 185 355" style="display:none"></path>':
    '<path id="sig-cable-pv2" class="sig-cable" d="M 475 600 L 350 560 L 290 535 L 185 495 L 185 390" style="display:none"></path>',

    # Netz: nur EIN Winkel.
    # Vom zentralen Knoten horizontal direkt in das Smartmeter,
    # danach senkrecht nach unten in den Boden.
    '<path id="sig-cable-grid" class="sig-cable" d="M 600 645 L 790 645 L 790 695 L 855 695 L 855 830"></path>':
    '<path id="sig-cable-grid" class="sig-cable" d="M 600 645 L 790 645 L 790 830"></path>',
}

for old, new in static_replacements.items():
    if old not in code:
        raise RuntimeError(f"Statischer Pfad nicht gefunden:\n{old}")
    code = code.replace(old, new, 1)

# 2) Farbige dynamische Pfade exakt identisch
dynamic_replacements = {
    "'M 505 560 L 505 175',":
    "'M 505 560 L 505 245',",

    "'M 505 560 L 350 560 L 350 535 L 185 535 L 185 355',":
    "'M 475 600 L 350 560 L 290 535 L 185 495 L 185 390',",

    "'M 600 645 L 790 645 L 790 695 L 855 695 L 855 830',":
    "'M 600 645 L 790 645 L 790 830',",
}

for old, new in dynamic_replacements.items():
    if old not in code:
        raise RuntimeError(f"Dynamischer Pfad nicht gefunden:\n{old}")
    code = code.replace(old, new, 1)

# Kommentare aktualisieren
code = code.replace(
    "// PV 1: vom zentralen Knoten senkrecht nach oben bis in die Dach-Panels.",
    "// PV 1: senkrecht bis an den unteren Beginn der Dach-Panels."
)
code = code.replace(
    "// PV 2: vom zentralen Bereich entlang des Wallbox-Bereichs und dann senkrecht\n"
    "        // nach oben bis direkt unter die Carport-Panels.",
    "// PV 2: nutzt zunächst die Wallbox-Trasse und steigt links senkrecht\n"
    "        // bis direkt unter die Carport-Panels."
)
code = code.replace(
    "// Netz: rechtwinklig zum Smartmeter und von dort senkrecht in den Boden.",
    "// Netz: vom zentralen Punkt direkt zum Smartmeter und mit genau einem\n"
    "        // 90°-Winkel senkrecht in den Boden."
)

out = Path("/mnt/data/module_sigenergy_linien_v2.php")
out.write_text(code, encoding="utf-8")

check = subprocess.run(["php", "-l", str(out)], capture_output=True, text=True)
if check.returncode != 0:
    raise RuntimeError(check.stdout + check.stderr)

print(check.stdout.strip())
print(out)
STDOUT/STDERR
No syntax errors detected in /mnt/data/module_sigenergy_linien_v2.php
/mnt/data/module_sigenergy_linien_v2.php
Jetzt ist nur der Code angepasst, ausgehend von der zuletzt funktionierenden Version.

Geändert:

Netz: nur noch ein Winkel: vom zentralen Punkt zum Smartmeter und dann senkrecht in den Boden.

Carport-PV: nutzt unten die Wallbox-Trasse und geht erst links senkrecht zu den Carport-Panels hoch.

Dach-PV: endet jetzt am unteren Beginn der Dach-Panels und läuft nicht mehr mitten hinein.

Haus, Batterie und Wallbox sonst unverändert.

PHP-Syntax geprüft: keine Fehler.

module_sigenergy_linien_v2.php 


Bibliothek
/
module_sigenergy_linien_v2.php


<?php

/*
 * Hausansicht:
 * Visual assets and calibrated flow geometry adapted from
 * SpengeSec/Genergy-Dashboard
 * https://github.com/SpengeSec/Genergy-Dashboard
 *
 * Licensed under CC BY-NC-SA 4.0:
 * https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * Changes: Integration into the IP-Symcon Energiefluss module,
 * live-data binding, colors and animation logic adapted.
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
            // Grafische Layer der Hausansicht aus dem Modulordner nach /user/
            // kopieren, damit sie im Browser der HTML-SDK-Kachel erreichbar sind.
            $this->EnsureHouseAssets();

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

    /* Hausansicht – Sigenergy/Genergy Layer-Komposition
       Visual assets adapted from SpengeSec/Genergy-Dashboard,
       CC BY-NC-SA 4.0. */
    #house-stage {
        position: relative;
        width: 900px;
        height: 640px;
        display: __HOUSE_DISPLAY__;
        overflow: hidden;
        border-radius: 18px;
        box-sizing: border-box;
        border: 1px solid #263443;
        background:
            radial-gradient(circle at 50% 35%, rgba(36, 52, 68, .35), transparent 48%),
            linear-gradient(180deg, #0d151e 0%, #090f15 100%);
        color: #f1f5f8;
    }

    /* Das eigentliche Sigenergy-Haus hat exakt das Seitenverhältnis
       der Original-Layer: 1170 x 1013. */
    #sig-scene {
        position: absolute;
        left: 80px;
        top: 0;
        width: 740px;
        height: 640px;
        overflow: visible;
    }

    .sig-layer,
    #sig-flow-svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }

    .sig-layer {
        object-fit: fill;
        pointer-events: none;
        user-select: none;
    }

    #sig-home-layer     { z-index: 1; }
    #sig-battery-layer  { z-index: 2; }
    #sig-meter-layer    { z-index: 3; }
    #sig-charger-layer  { z-index: 4; }

    #sig-flow-svg {
        z-index: 5;
        overflow: visible;
        pointer-events: none;
    }

    #sig-flow-svg .sig-cable {
        fill: none;
        stroke: rgba(135, 145, 154, .42);
        stroke-width: 5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    #sig-flow-svg .flow-path {
        fill: none;
        stroke-width: 6;
        stroke-linecap: round;
        stroke-linejoin: round;
        opacity: .96;
        filter: drop-shadow(0 0 3px currentColor);
    }

    #sig-flow-svg .soc-track {
        fill: none;
        stroke: rgba(140,150,160,.28);
        stroke-width: 7;
    }

    #sig-flow-svg .soc-ring {
        fill: none;
        stroke-width: 7;
        stroke-linecap: round;
        transform-origin: 498px 585px;
        transform: rotate(-90deg);
    }

    .sig-label {
        position: absolute;
        z-index: 8;
        min-width: 112px;
        padding: 6px 8px;
        box-sizing: border-box;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,.09);
        background: rgba(7, 12, 17, .76);
        box-shadow: 0 5px 16px rgba(0,0,0,.18);
        line-height: 1.18;
        pointer-events: none;
        backdrop-filter: blur(2px);
    }

    .sig-label .primary {
        color: #f3f6f8;
        font-size: 15px;
        font-weight: 700;
        white-space: nowrap;
    }

    .sig-label .secondary {
        color: #8f9ba6;
        font-size: 10px;
        font-weight: 600;
        margin-top: 2px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .sig-label .detail {
        color: #aab5bf;
        font-size: 9px;
        margin-top: 3px;
        line-height: 1.25;
    }

    /* Positionen entsprechend der veröffentlichten Sigenergy-House-Card. */
    #sig-solar-label {
        top: 2%;
        left: 36%;
        border-color: rgba(239,160,32,.28);
    }

    #sig-home-label {
        top: 39%;
        left: 62%;
        border-color: rgba(77,159,255,.28);
    }

    #sig-solar2-label {
        top: 27%;
        left: 2%;
        border-color: rgba(239,160,32,.28);
        display: none;
    }

    #sig-battery-label {
        top: 72%;
        left: 28%;
        border-color: rgba(90,200,100,.25);
    }

    #sig-grid-label {
        top: 65%;
        left: 72%;
        border-color: rgba(255,80,70,.25);
    }

    #sig-wallbox-label {
        top: 54%;
        left: 1%;
        border-color: rgba(34,211,208,.28);
    }

    .c-solar { color: #EFA020 !important; }
    .c-import { color: #ff4d43 !important; }
    .c-export { color: #6fd32f !important; }
    .c-discharge { color: #3ca0ff !important; }
    .c-charge { color: #6fd32f !important; }
    .c-wallbox { color: #22d3d0 !important; }
    .c-home { color: #4d9fff !important; }

    /* Die alten Haus-Karten/Labels der Zwischenversion sind in dieser
       Darstellung nicht mehr erforderlich. */
    #house-pv-list,
    #house-grid-label,
    #house-home-label,
    #house-battery-label,
    #house-wallbox-label,
    .house-card-grid,
    .house-topbar {
        display: none !important;
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

                    <!-- Hausansicht: Sigenergy/Genergy Layer-Komposition -->
                    <div id="house-stage">
                        <div id="sig-scene">
                            <img
                                id="sig-home-layer"
                                class="sig-layer"
                                src="/user/Energiefluss/Sigenergy/home_has_solar_has_car.png"
                                alt=""
                            >
                            <img
                                id="sig-battery-layer"
                                class="sig-layer"
                                src="/user/Energiefluss/Sigenergy/sigenstor_home.png"
                                alt=""
                            >
                            <img
                                id="sig-meter-layer"
                                class="sig-layer"
                                src="/user/Energiefluss/Sigenergy/ammeter_home.png"
                                alt=""
                            >
                            <img
                                id="sig-charger-layer"
                                class="sig-layer"
                                src="/user/Energiefluss/Sigenergy/ac_charger_bg.png"
                                alt=""
                            >

                            <!--
                                ViewBox und Pfade entsprechen den nativen
                                1170x1013-Grafik-Layern. Dadurch skalieren
                                Bilder und Energiepfade als eine Einheit.
                            -->
                            <svg
                                id="sig-flow-svg"
                                viewBox="0 0 1170 1013"
                                preserveAspectRatio="none"
                                aria-hidden="true"
                            >
                                <g id="sig-static-cables">
                                    <!-- PV Hausdach direkt zum SigenStor / zentralen Knoten -->
                                    <path id="sig-cable-pv1" class="sig-cable" d="M 505 560 L 505 245"></path>

                                    <!-- Zweite PV-Anlage auf dem Carport; nur sichtbar wenn PV2 konfiguriert -->
                                    <path id="sig-cable-pv2" class="sig-cable" d="M 475 600 L 350 560 L 290 535 L 185 495 L 185 390" style="display:none"></path>

                                    <!-- Hausverbrauch vom zentralen Knoten direkt ins Gebäude -->
                                    <path id="sig-cable-home" class="sig-cable" d="M 535 570 L 680 570 L 680 455 L 755 455"></path>

                                    <!-- Batterie -->
                                    <path class="sig-cable" d="M 490 570 L 492 785"></path>

                                    <!-- Netz endet am Smartmeter, nicht weiter im Haus -->
                                    <path id="sig-cable-grid" class="sig-cable" d="M 600 645 L 790 645 L 790 830"></path>

                                    <!-- Wallbox; nur bei konfigurierter Wallbox sichtbar -->
                                    <path id="sig-cable-wallbox" class="sig-cable" d="M 75 485 L 75 455 L 290 535 L 350 560 L 475 600"></path>
                                </g>

                                <g id="house-flow-lines"></g>
                                <g id="house-flow-dots"></g>

                                <circle class="soc-track" cx="498" cy="585" r="32"></circle>
                                <circle
                                    id="sig-soc-ring"
                                    class="soc-ring"
                                    cx="498"
                                    cy="585"
                                    r="32"
                                    stroke="#6fd32f"
                                ></circle>
                            </svg>

                            <div id="sig-solar-label" class="sig-label">
                                <div id="sig-solar-power" class="primary c-solar">0 W</div>
                                <div id="sig-solar-name" class="secondary">PV Dach</div>
                                <div id="sig-solar-detail" class="detail"></div>
                            </div>

                            <div id="sig-solar2-label" class="sig-label">
                                <div id="sig-solar2-power" class="primary c-solar">0 W</div>
                                <div id="sig-solar2-name" class="secondary">PV Carport</div>
                                <div id="sig-solar2-detail" class="detail"></div>
                            </div>

                            <div id="sig-home-label" class="sig-label">
                                <div id="sig-home-power" class="primary c-home">0 W</div>
                                <div class="secondary">Haus</div>
                            </div>

                            <div id="sig-battery-label" class="sig-label">
                                <div id="sig-battery-power" class="primary c-discharge">0 W · 0 %</div>
                                <div id="sig-battery-name" class="secondary">Batterie</div>
                                <div id="sig-battery-detail" class="detail"></div>
                            </div>

                            <div id="sig-grid-label" class="sig-label">
                                <div id="sig-grid-power" class="primary c-import">0 W</div>
                                <div class="secondary">Netz</div>
                                <div id="sig-grid-detail" class="detail"></div>
                            </div>

                            <div id="sig-wallbox-label" class="sig-label">
                                <div id="sig-wallbox-power" class="primary c-wallbox">0 W</div>
                                <div id="sig-wallbox-name" class="secondary">Wallbox</div>
                                <div id="sig-wallbox-detail" class="detail"></div>
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

        const pv1 = pvs.length > 0 ? pvs[0] : null;
        const pv2 = pvs.length > 1 ? pvs[1] : null;
        const pvTotal = pvs.reduce((sum, pv) => sum + (pv.value || 0), 0);
        const batteryTotal = batteries.reduce((sum, bat) => sum + (bat.value || 0), 0);

        const mainBattery = batteries.length ? batteries[0] : null;
        const mainSoc = mainBattery ? Math.max(0, Math.min(100, mainBattery.soc || 0)) : 0;

        // Eine Wallbox gilt nur dann als konfiguriert, wenn im Payload
        // tatsächlich eine Wallbox-Leistungsvariable hinterlegt ist.
        const hasWallbox = !!d.hasWallbox;

        // ---- PV 1 = Hausdach ------------------------------------------------
        const solarPower = document.getElementById('sig-solar-power');
        const solarName = document.getElementById('sig-solar-name');
        const solarDetail = document.getElementById('sig-solar-detail');

        if (solarPower) solarPower.textContent = fmt(pv1 ? (pv1.value || 0) : 0);
        if (solarName) solarName.textContent = pv1?.name || 'PV Dach';
        if (solarDetail) solarDetail.textContent = pv1?.energy || '';

        // ---- PV 2 = Carport -------------------------------------------------
        const solar2Label = document.getElementById('sig-solar2-label');
        const solar2Power = document.getElementById('sig-solar2-power');
        const solar2Name = document.getElementById('sig-solar2-name');
        const solar2Detail = document.getElementById('sig-solar2-detail');

        if (solar2Label) solar2Label.style.display = pv2 ? '' : 'none';
        if (solar2Power) solar2Power.textContent = fmt(pv2 ? (pv2.value || 0) : 0);
        if (solar2Name) solar2Name.textContent = pv2?.name || 'PV Carport';
        if (solar2Detail) solar2Detail.textContent = pv2?.energy || '';

        const pv2Base = document.getElementById('sig-cable-pv2');
        if (pv2Base) pv2Base.style.display = pv2 ? '' : 'none';

        // ---- Hausverbrauch --------------------------------------------------
        const homePower = document.getElementById('sig-home-power');
        if (homePower) homePower.textContent = fmt(haus);

        // ---- Netz -----------------------------------------------------------
        const gridColor = grid >= 0 ? AC.import : AC.export;
        const gridMode = grid >= 0 ? 'Bezug' : 'Einspeisung';

        const gridPower = document.getElementById('sig-grid-power');
        const gridDetail = document.getElementById('sig-grid-detail');

        if (gridPower) {
            gridPower.textContent = fmt(Math.abs(grid));
            gridPower.style.color = gridColor;
        }

        if (gridDetail) {
            const details = [gridMode];
            if (d.gridImportEnergy) details.push('Bezug: ' + d.gridImportEnergy);
            if (d.gridExportEnergy) details.push('Einspeisung: ' + d.gridExportEnergy);
            gridDetail.innerHTML = details.join('<br>');
            gridDetail.style.color = gridColor;
        }

        // ---- Batterie -------------------------------------------------------
        const batColor = batteryTotal >= 0 ? AC.discharge : AC.charge;
        const batMode = batteryTotal >= 0 ? 'Entladen' : 'Laden';

        const batteryPower = document.getElementById('sig-battery-power');
        const batteryName = document.getElementById('sig-battery-name');
        const batteryDetail = document.getElementById('sig-battery-detail');

        if (batteryPower) {
            batteryPower.textContent = `${fmt(Math.abs(batteryTotal))} · ${Math.round(mainSoc)} %`;
            batteryPower.style.color = batColor;
        }
        if (batteryName) {
            batteryName.textContent = mainBattery?.name || 'Batterie';
        }
        if (batteryDetail) {
            batteryDetail.innerHTML = batteries.map((bat, i) => {
                const mode = (bat.value || 0) >= 0 ? 'Entladen' : 'Laden';
                return `${bat.name || ('Batterie ' + (i + 1))}: ${Math.round(bat.soc || 0)} % · ${fmt(Math.abs(bat.value || 0))} ${mode}`;
            }).join('<br>') || batMode;
        }

        // ---- Wallbox --------------------------------------------------------
        const wallboxLayer = document.getElementById('sig-charger-layer');
        const wallboxLabel = document.getElementById('sig-wallbox-label');
        const wallboxBase = document.getElementById('sig-cable-wallbox');

        if (wallboxLayer) wallboxLayer.style.display = hasWallbox ? '' : 'none';
        if (wallboxLabel) wallboxLabel.style.display = hasWallbox ? '' : 'none';
        if (wallboxBase) wallboxBase.style.display = hasWallbox ? '' : 'none';

        const wallboxPower = document.getElementById('sig-wallbox-power');
        const wallboxName = document.getElementById('sig-wallbox-name');
        const wallboxDetail = document.getElementById('sig-wallbox-detail');

        if (wallboxPower) wallboxPower.textContent = fmt(wallbox.value || 0);
        if (wallboxName) wallboxName.textContent = wallbox.name || 'Wallbox';
        if (wallboxDetail) wallboxDetail.textContent = wallbox.energy || '';

        // ---- Batterie-SOC-Ring ---------------------------------------------
        const socRing = document.getElementById('sig-soc-ring');
        if (socRing) {
            const r = 32;
            const circumference = 2 * Math.PI * r;
            socRing.setAttribute('stroke-dasharray', `${circumference}`);
            socRing.setAttribute(
                'stroke-dashoffset',
                `${circumference * (1 - mainSoc / 100)}`
            );
            socRing.setAttribute('stroke', batColor);
        }

        // ---- Dynamische Energiepfade ---------------------------------------

        // PV 1: senkrecht bis an den unteren Beginn der Dach-Panels.
        if (pv1 && (pv1.value || 0) > 0) {
            addHouseEdge(
                'house-pv1',
                'M 505 560 L 505 245',
                AC.solar,
                6
            );
            houseEdgeState['house-pv1'] = {
                w: Math.max(pv1.value || 0, 0),
                rev: false
            };
        }

        // PV 2: nutzt zunächst die Wallbox-Trasse und steigt links senkrecht
        // bis direkt unter die Carport-Panels.
        if (pv2 && (pv2.value || 0) > 0) {
            addHouseEdge(
                'house-pv2',
                'M 475 600 L 350 560 L 290 535 L 185 495 L 185 390',
                AC.solar,
                6
            );
            houseEdgeState['house-pv2'] = {
                w: Math.max(pv2.value || 0, 0),
                rev: false
            };
        }

        // Hausverbrauch: rechtwinklig vom zentralen Knoten direkt ins Haus.
        if (haus > 0) {
            addHouseEdge(
                'house-home',
                'M 535 570 L 680 570 L 680 455 L 755 455',
                AC.home,
                6
            );
            houseEdgeState['house-home'] = {
                w: haus,
                rev: false
            };
        }

        // Batterie.
        if (Math.abs(batteryTotal) > 0) {
            addHouseEdge(
                'house-battery',
                'M 490 570 L 492 785',
                batColor,
                6
            );
            houseEdgeState['house-battery'] = {
                w: Math.abs(batteryTotal),
                rev: batteryTotal >= 0
            };
        }

        // Netz: vom zentralen Punkt direkt zum Smartmeter und mit genau einem
        // 90°-Winkel senkrecht in den Boden.
        if (Math.abs(grid) > 0) {
            addHouseEdge(
                'house-grid',
                'M 600 645 L 790 645 L 790 830',
                gridColor,
                6
            );
            houseEdgeState['house-grid'] = {
                w: Math.abs(grid),
                rev: grid >= 0
            };
        }

        // Wallbox nur, wenn eine Wallbox-Leistungsvariable konfiguriert ist.
        if (hasWallbox && (wallbox.value || 0) > 0) {
            addHouseEdge(
                'house-wallbox',
                'M 75 485 L 75 455 L 290 535 L 350 560 L 475 600',
                AC.wallbox,
                6
            );
            houseEdgeState['house-wallbox'] = {
                w: Math.abs(wallbox.value || 0),
                rev: true
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

    private function EnsureHouseAssets(): void
    {
        $sourceDir = __DIR__;
        $targetDir = IPS_GetKernelDir()
            . 'user'
            . DIRECTORY_SEPARATOR
            . 'Energiefluss'
            . DIRECTORY_SEPARATOR
            . 'Sigenergy';

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                $this->LogMessage(
                    'Hausansicht: Zielverzeichnis konnte nicht erstellt werden: ' . $targetDir,
                    KL_ERROR
                );
                return;
            }
        }

        $assets = [
            'home_has_solar_has_car.png',
            'sigenstor_home.png',
            'ammeter_home.png',
            'ac_charger_bg.png',
        ];

        foreach ($assets as $asset) {
            $source = $sourceDir . DIRECTORY_SEPARATOR . $asset;
            $target = $targetDir . DIRECTORY_SEPARATOR . $asset;

            if (!is_file($source)) {
                $this->LogMessage(
                    'Hausansicht: Datei fehlt im Modulordner: ' . $source,
                    KL_ERROR
                );
                continue;
            }

            $copy = !is_file($target);

            if (!$copy) {
                $copy =
                    @filesize($source) !== @filesize($target)
                    || @filemtime($source) > @filemtime($target);
            }

            if ($copy) {
                if (!@copy($source, $target)) {
                    $this->LogMessage(
                        'Hausansicht: Datei konnte nicht kopiert werden: ' . $asset,
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
