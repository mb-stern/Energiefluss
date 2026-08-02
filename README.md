# Modul zur Visualisierung des Energieflusses für IP-Symcon
Folgende Funktionen beinhaltet das Energiefluss Symcon Repository

- __Energiefluss__ ([Dokumentation](Energie))   

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

## Third-Party Components

Dieses Modul verwendet folgende Open-Source-Komponenten:

| Komponente | Lizenz | Verwendung |
|------------|---------|------------|
| Sunsynk Power Flow Card | MIT | Technische Energieflussdarstellung (Compact, Lite, Full) |
| Power Flow Card (LordGuenni) | MIT | Hausgrafik |
| Lit (Google LLC) | BSD-3-Clause | Web Components Framework |

Die vollständigen Lizenztexte befinden sich im Verzeichnis `licenses/`.

Alle Rechte an den jeweiligen Drittkomponenten verbleiben bei deren ursprünglichen Autoren.