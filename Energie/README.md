# ⚡ Energiefluss für IP-Symcon

Ein modernes Visualisierungsmodul für **IP-Symcon**, mit dem sich die Energieflüsse eines Hauses übersichtlich und in Echtzeit darstellen lassen.

Das Modul visualisiert unter anderem:

* ☀️ Photovoltaik-Anlagen
* 🔋 Batteriespeicher
* 🏠 Hausverbrauch
* ⚡ Netzbezug und Netzeinspeisung
* 🚗 Wallbox / Elektrofahrzeug
* 🔌 frei definierbare Verbraucher

Für die Visualisierung stehen **zwei unterschiedliche Ansichten** zur Verfügung:

1. **Klassische Energieflussansicht**
2. **Grafische Hausansicht**

Die Werte werden automatisch aktualisiert, sobald sich eine der verwendeten IP-Symcon-Variablen ändert.

---

## ✨ Funktionen

### ☀️ Mehrere PV-Anlagen

Es können mehrere PV-Anlagen bzw. Wechselrichter eingebunden werden.

Für jede Anlage können konfiguriert werden:

* individueller Name
* aktuelle Leistung in Watt
* erzeugte Energie in kWh

Die Leistungen der einzelnen Anlagen werden automatisch zur gesamten PV-Leistung zusammengefasst.

---

### 🔋 Mehrere Batteriespeicher

Auch mehrere Batteriespeicher können gleichzeitig verwendet werden.

Pro Batterie stehen folgende Werte zur Verfügung:

* Name
* aktuelle Lade-/Entladeleistung
* Ladezustand (SOC)
* Entladeenergie
* Ladeenergie
* optionale Umkehrung der Flussrichtung

Die Visualisierung erkennt automatisch, ob die Batterie gerade geladen oder entladen wird.

---

### ⚡ Netzbezug und Einspeisung

Das Modul unterstützt die Darstellung von:

* aktuellem Netzbezug
* aktueller Netzeinspeisung
* gesamter bezogener Energie
* gesamter eingespeister Energie

Falls das verwendete Messgerät ein umgekehrtes Vorzeichen liefert, kann die Netzleistung direkt in der Modulkonfiguration invertiert werden.

---

### 🚗 Wallbox

Eine Wallbox kann optional in die Energieflussdarstellung integriert werden.

Unterstützt werden:

* frei wählbarer Name
* aktuelle Ladeleistung
* geladene Energie
* Fahrzeug-SOC

Der Fahrzeug-SOC kann dabei auch über einen **IP-Symcon-Link** eingebunden werden.

Ist keine Wallbox konfiguriert, wird sie automatisch aus der Visualisierung ausgeblendet.

---

### 🔌 Zusätzliche Verbraucher

Neben der Wallbox können beliebig weitere Verbrauchergruppen definiert werden.

Beispiele:

* Wärmepumpe
* Boiler
* Küche
* Waschmaschine
* Server
* Klimaanlage
* Pool
* Werkstatt

Für jeden Verbraucher können hinterlegt werden:

* Name
* aktuelle Leistung
* optionaler Tagesverbrauch
* frei wählbares Icon

Die Verbraucher werden automatisch in die Energieflussdarstellung integriert.

---

# 🖥️ Zwei Visualisierungsmodi

## 1. Klassische Energieflussansicht

Die klassische Ansicht stellt alle Energiequellen und Verbraucher als übersichtliche Knoten dar.

Animierte Punkte zeigen dabei die aktuelle Flussrichtung der Energie.

Dargestellt werden unter anderem:

* PV → Haus
* Batterie → Haus
* Haus → Batterie
* Netz → Haus
* Haus → Netz
* Haus → Verbraucher
* Haus → Wallbox

Die Geschwindigkeit der Animation orientiert sich an der übertragenen Leistung.

---

## 2. Grafische Hausansicht

Zusätzlich steht eine grafische Hausansicht zur Verfügung.

Sie zeigt die Energieflüsse direkt innerhalb einer Hausgrafik und kombiniert diese mit Informationsfeldern für:

* PV
* Hausverbrauch
* Batterie
* Netz
* Wallbox

Die Hausansicht basiert auf der Open-Source **Power Flow Card von LordGuenni**, die für die Verwendung innerhalb von IP-Symcon angepasst und in das Modul integriert wurde.

Die benötigten Dateien werden lokal mit dem Modul ausgeliefert. Für die Visualisierung ist daher keine externe CDN-Verbindung erforderlich.

---

# 📱 Responsive Darstellung

Die Visualisierung passt sich automatisch an die verfügbare Größe der IP-Symcon-Kachel an.

Dabei werden unter anderem unterschiedliche Darstellungen für:

* Desktop
* Tablet
* Smartphone

verwendet.

Insbesondere die kompakte Smartphone-Ansicht reduziert die dargestellten Zusatzinformationen und vergrößert wichtige Leistungswerte für eine bessere Lesbarkeit.

---

# 🎨 Konfigurierbare Farben

Die Farben der Energieflüsse können direkt in der Instanzkonfiguration angepasst werden.

Konfigurierbar sind:

* ☀️ PV
* ⚡ Netzbezug
* 🌐 Netzeinspeisung
* 🔋 Batterie laden
* 🔋 Batterie entladen
* 🔌 Verbraucher

Zusätzlich kann die Geschwindigkeit der Flussanimation angepasst werden.

---

# 🏠 Anpassbare Hausgrafik

Auch die Farben der grafischen Hausansicht können individuell verändert werden.

Konfigurierbar sind unter anderem:

* Haus / Fassade
* Hauptdach
* Nebendach
* Fenster / Beleuchtung
* PV-Module
* Wechselrichter
* Fahrzeug
* Fahrzeugdetails
* Batteriegehäuse
* Batterie-Akzentfarbe

Über **„Standardfarben wiederherstellen“** können jederzeit die ursprünglichen Farben der Hausgrafik wiederhergestellt werden.

---

# 🔄 Umschalten der Ansicht

Zwischen

**Energiefluss**

und

**Hausansicht**

kann direkt innerhalb der Visualisierung umgeschaltet werden.

Der aktuell gewählte Modus wird in der Instanz gespeichert.

---

# 📊 Berechnung des Hausverbrauchs

Der Hausverbrauch wird aus den vorhandenen Energieflüssen automatisch berechnet.

Dabei berücksichtigt das Modul:

* PV-Produktion
* Batterieentladung
* Batterieladung
* Netzbezug
* Netzeinspeisung

Dadurch ist keine zwingend separate Variable für den aktuellen Hausverbrauch erforderlich.

---

# 🚀 Installation

Das Repository über den **IP-Symcon Module Store** bzw. die Modulverwaltung installieren.

Alternativ kann das Repository als benutzerdefiniertes Modul hinzugefügt werden.

Nach der Installation:

1. Neue Instanz vom Typ **Energiefluss** erstellen.
2. Gewünschte PV-Anlagen konfigurieren.
3. Batteriespeicher konfigurieren.
4. Netzleistung auswählen.
5. Optional Energiezähler für Bezug und Einspeisung auswählen.
6. Optional Wallbox konfigurieren.
7. Optional weitere Verbraucher hinzufügen.
8. Gewünschte Farben einstellen.
9. Änderungen übernehmen.
10. Die Visualisierung einer Kachel hinzufügen.

---

# ⚙️ Konfiguration

## PV & Batterie

### PV-Anlagen

| Einstellung | Beschreibung                  |
| ----------- | ----------------------------- |
| Name        | Bezeichnung der PV-Anlage     |
| Leistung    | aktuelle Leistung in Watt     |
| Energie     | erzeugte Gesamtenergie in kWh |

### Batterien

| Einstellung    | Beschreibung                      |
| -------------- | --------------------------------- |
| Name           | Bezeichnung des Batteriespeichers |
| Leistung       | aktuelle Lade-/Entladeleistung    |
| SOC            | Ladezustand in Prozent            |
| Entladeenergie | gesamte abgegebene Energie        |
| Ladeenergie    | gesamte geladene Energie          |
| Fluss umkehren | invertiert die Flussrichtung      |

---

## Netz

| Einstellung            | Beschreibung                         |
| ---------------------- | ------------------------------------ |
| Netzleistung           | aktuelle Netzleistung                |
| Vorzeichen umkehren    | invertiert die Netzleistung          |
| Rücklieferung Leistung | optionale separate Einspeiseleistung |
| Netzbezug gesamt       | bezogene Energie in kWh              |
| Einspeisung gesamt     | eingespeiste Energie in kWh          |

---

## Wallbox

| Einstellung  | Beschreibung              |
| ------------ | ------------------------- |
| Name         | Bezeichnung der Wallbox   |
| Ladeleistung | aktuelle Ladeleistung     |
| Ladeenergie  | geladene Energie          |
| Fahrzeug-SOC | Ladezustand des Fahrzeugs |

---

## Verbraucher

Für zusätzliche Verbraucher können beliebig viele Einträge angelegt werden.

| Einstellung        | Beschreibung                        |
| ------------------ | ----------------------------------- |
| Name               | Name des Verbrauchers               |
| Leistungs-Variable | aktuelle Leistungsaufnahme          |
| Tagesverbrauch     | optionaler Energieverbrauch         |
| Icon               | Symbol innerhalb der Visualisierung |

---

# 🔢 Vorzeichen

Die korrekte Flussrichtung hängt von den verwendeten Variablen ab.

## Batterie

Standardmäßig gilt:

```text
positive Leistung  = Batterie entlädt
negative Leistung  = Batterie lädt
```

Falls das verwendete System die Werte genau umgekehrt liefert, kann **„Fluss umkehren“** aktiviert werden.

## Netz

Standardmäßig gilt:

```text
positive Leistung  = Netzbezug
negative Leistung  = Netzeinspeisung
```

Auch dieses Verhalten kann über **„Vorzeichen der Netzleistung umkehren“** angepasst werden.

---

# 🎨 Standardfarben

Die Standarddarstellung verwendet unterschiedliche Farben für die jeweiligen Energieflüsse:

| Energiefluss             | Farbe    |
| ------------------------ | -------- |
| ☀️ PV                    | Gelb     |
| ⚡ Netzbezug              | Rot      |
| 🌐 Netzeinspeisung       | Grün     |
| 🔋 Batterie laden        | Hellblau |
| 🔋 Batterie entladen     | Blau     |
| 🔌 Verbraucher / Wallbox | Türkis   |

Alle Farben können über die Instanzkonfiguration geändert werden.

---

# ⚡ Automatische Aktualisierung

Das Modul registriert die konfigurierten IP-Symcon-Variablen automatisch.

Ändert sich ein Wert, wird die Visualisierung aktualisiert.

Dadurch ist kein permanentes Neuladen der kompletten HTML-Kachel notwendig.

---

# 📁 Modulstruktur

Die für die Hausansicht benötigten Dateien werden zusammen mit dem Modul ausgeliefert.

Beispiel:

```text
Energiefluss/
├── module.php
├── module.json
├── locale.json
├── README.md
└── assets/
    └── vendor/
        └── power-flow-card.js
```

Beim Anwenden der Instanzkonfiguration veröffentlicht das Modul die benötigten Visualisierungsdateien automatisch im IP-Symcon-Webbereich.

---

# 🧩 Power Flow Card

Für die grafische Hausansicht wird das Open-Source-Projekt:

**LordGuenni / power-flow-card**

verwendet.

Projekt:

https://github.com/LordGuenni/power-flow-card

Die Power Flow Card wurde ursprünglich für Home Assistant entwickelt und für dieses Modul an die Datenbereitstellung und Visualisierung von IP-Symcon angepasst.

Die Originaldateien werden lokal innerhalb des Modulbaums verwaltet.

---

# 📜 Lizenz & Drittanbieter

Dieses Modul enthält bzw. verwendet Komponenten aus dem Projekt **power-flow-card** von LordGuenni / Florian Stamer.

Die Power Flow Card steht unter der **MIT-Lizenz**.

Die jeweiligen Lizenz- und Copyright-Hinweise der verwendeten Drittanbieter-Komponenten bleiben bestehen.

Weitere Informationen befinden sich in den entsprechenden Lizenzdateien innerhalb des Repositories.

---

# 🛠️ Entwicklung

Das Modul befindet sich in aktiver Entwicklung.

Neue Funktionen, Optimierungen und Anpassungen der Visualisierung können laufend ergänzt werden.

Besonderer Fokus liegt auf:

* flexibler Unterstützung unterschiedlicher Energiesysteme
* mehreren PV-Anlagen und Batteriespeichern
* dynamischen Verbrauchern
* Wallbox-Integration
* responsiver Darstellung
* guter Smartphone-Darstellung
* frei konfigurierbaren Farben
* möglichst einfacher Einrichtung innerhalb von IP-Symcon

---

# 💡 Hinweise

Das Modul ist nicht an einen bestimmten Hersteller gebunden.

Es können grundsätzlich alle Geräte verwendet werden, deren Messwerte als IP-Symcon-Variablen zur Verfügung stehen.

Damit eignet sich die Visualisierung beispielsweise für Daten aus:

* Wechselrichtern
* Smart Metern
* Batteriespeichern
* Wallboxen
* Energiezählern
* Modbus-Geräten
* MQTT-Geräten
* Hersteller-APIs
* anderen IP-Symcon-Modulen

Entscheidend ist lediglich, dass die benötigten Leistungs- bzw. Energiewerte als Variablen in IP-Symcon vorhanden sind.

---

# ❤️ Credits

Vielen Dank an **LordGuenni / Florian Stamer** für die Entwicklung und Veröffentlichung der Power Flow Card.

Die Hausansicht dieses Moduls baut auf diesem Open-Source-Projekt auf und adaptiert dessen Visualisierung für IP-Symcon.

---

## ⚠️ Hinweis

Dieses Projekt ist ein unabhängiges Community-Projekt und steht in keiner offiziellen Verbindung zu IP-Symcon oder den Herstellern der angebundenen Energiegeräte.
