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
                    'onClick' => 'ENERGIE_Refresh($id);',
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
            $payload = json_encode(
                $this->BuildPayload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            // HTML ist direkt in diesem Modul integriert.
            return $this->GetVisualizationHtml() . '<script>handleMessage(' . $payload . ');</script>';
        } catch (Throwable $e) {
            return '<div style="padding:1em">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    private function GetVisualizationHtml(): string
    {
        return <<<'HTML'
<style>
    body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    :root {
        --w-bg: #f6f6f3; --w-surface: #ffffff; --w-text: #20201e;
        --w-text2: #6c6c66; --w-line: #d3d3ce; --w-border: #e6e6e1;
    }
    :root[data-theme="dark"] {
        --w-bg: #1b1b19; --w-surface: #272725; --w-text: #f1f1ee;
        --w-text2: #9a9a93; --w-line: #3b3b38; --w-border: #343431;
    }
    #eflow { border-radius: 12px; padding: 10px; background: var(--w-bg); }
    #fit { width: 100%; overflow: hidden; }
    #stage { position: relative; width: 1080px; height: 640px; transform-origin: 0 0; }
    #svg { position: absolute; inset: 0; z-index: 1; }
    #svg #lines line, #svg #lines path { stroke: var(--w-line); }
    .node { position: absolute; transform: translate(-50%, -50%); border-radius: 50%; background: var(--w-surface); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 2; text-align: center; }
    .node .val { color: var(--w-text); font-weight: 500; }
    .node .sub { color: var(--w-text2); }
    .lbl { position: absolute; left: 50%; transform: translateX(-50%); color: var(--w-text2); white-space: nowrap; }
    .lbl.top { bottom: 100%; margin-bottom: 8px; }
    .lbl.bot { top: 100%; margin-top: 8px; }
    #phases { position: absolute; left: 110px; top: 466px; transform: translateX(-50%); font-size: 13px; color: var(--w-text2); white-space: nowrap; }
    #wrap { display: flex; gap: 14px; align-items: flex-start; }
    #fit { flex: 1 1 auto; min-width: 0; }
    #cfg { flex: 0 0 250px; width: 250px; border-left: 0.5px solid var(--w-border); padding-left: 14px; }
    #cfgsec { margin-top: 12px; }
    #statsec + #cfgsec[style=""] { border-top: 0.5px solid var(--w-border); padding-top: 10px; }
    .cfg-h { font-size: 14px; font-weight: 500; color: var(--w-text); margin-bottom: 10px; }
    .cfg-live { display: flex; flex-direction: column; gap: 6px; margin-bottom: 6px; }
    .lv { background: var(--w-surface); border: 0.5px solid var(--w-border); border-radius: 8px; padding: 7px 10px; display: flex; justify-content: space-between; font-size: 13px; }
    .lv span { color: var(--w-text2); } .lv b { color: var(--w-text); font-weight: 500; }
    .cfg-sub { font-size: 12px; font-weight: 500; color: var(--w-text2); margin: 10px 0 2px; }
    .cfg-grid { display: grid; grid-template-columns: 1fr; gap: 2px; }
    .fld { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 13px; padding: 3px 0; }
    .fld > span { color: var(--w-text2); }
    .ed { color: var(--w-text); font-weight: 500; border: 0.5px solid var(--w-border); background: var(--w-surface); border-radius: 6px; padding: 4px 8px; width: 92px; text-align: right; font-size: 13px; font-family: inherit; }
</style>
<script src="/icons.js"></script>

<div id="eflow">
    <div id="wrap">
        <div id="fit">
            <div id="stage">
                <svg id="svg" width="1080" height="640" viewBox="0 0 1080 640" aria-hidden="true">
                    <g id="lines" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></g>
                    <g id="ring"></g>
                    <g id="dots"></g>
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

<script>
    // Theme-Erkennung: Symcon injiziert die Designfarben (u.a. --content-color bzw.
    // die Textfarbe auf <body>) in die Kachel. Helle Schrift => dunkles Design.
    function detectTheme() {
        let probe = getComputedStyle(document.documentElement).getPropertyValue('--content-color').trim();
        if (!probe) probe = getComputedStyle(document.body).color;
        let dark = null;
        const m = probe && probe.match(/rgba?\((\d+)[,\s]+(\d+)[,\s]+(\d+)/);
        if (m) {
            const lum = (0.299 * m[1] + 0.587 * m[2] + 0.114 * m[3]) / 255;
            dark = lum > 0.5;
        } else if (probe && probe[0] === '#' && probe.length >= 7) {
            const r = parseInt(probe.substr(1, 2), 16), g = parseInt(probe.substr(3, 2), 16), b = parseInt(probe.substr(5, 2), 16);
            dark = (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.5;
        }
        if (dark === null) dark = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    }
    detectTheme();
    window.addEventListener('load', detectTheme);
    setInterval(detectTheme, 2000);

    const AC = { solar: '#EFA020', grid: '#3B82C4', room: '#2FA98F', batt: '#4F9A5B' };
    const NSc = 'http://www.w3.org/2000/svg';
    const RR = 34, COL0 = 530, COLW = 120;

    const MAIN = {
        sf:   { x: 250, y: 82,  r: 46, ic: 'solar-panel',   icc: AC.solar, lab: 'SolarFlow', lp: 'top' },
        hm:   { x: 470, y: 82,  r: 46, ic: 'solar-panel',   icc: AC.solar, lab: 'Hoymiles',  lp: 'top' },
        batt: { x: 360, y: 232, r: 50, ic: 'battery-half',  icc: AC.batt,  lab: 'Batterie',  lp: 'top', ring: true },
        netz: { x: 110, y: 400, r: 46, ic: 'bolt',          icc: AC.grid,  lab: 'Netz',      lp: 'bot' },
        haus: { x: 360, y: 400, r: 52, ic: 'house',         icc: 'var(--w-text)', lab: 'Haus', lp: 'bot', ring: true }
    };

    const stage = document.getElementById('stage');
    const linesG = document.getElementById('lines');
    const dotsG = document.getElementById('dots');
    const ringG = document.getElementById('ring');

    function gpos(i) {
        const col = Math.floor(i / 2), top = i % 2 === 0;
        return { x: COL0 + col * COLW, y: top ? 252 : 548, lp: top ? 'top' : 'bot' };
    }
    function fmt(w) { return Math.round(w || 0).toLocaleString('de-DE') + ' W'; }

    function addNode(id, n, cls) {
        const el = document.createElement('div');
        el.className = 'node' + (cls ? ' ' + cls : '');
        el.id = 'n-' + id;
        const border = n.ring ? 'transparent' : n.icc;
        const isz = n.r < 40 ? 18 : 22;
        el.style.cssText = `left:${n.x}px;top:${n.y}px;width:${n.r * 2}px;height:${n.r * 2}px;border:3px solid ${border};`;
        el.innerHTML = `<div class="lbl ${n.lp}" style="font-size:${n.r < 40 ? 12 : 14}px">${n.lab}</div>` +
            `<i class="fa-solid fa-${n.ic}" style="font-size:${isz}px;color:${n.icc}"></i>` +
            `<div class="body" id="body-${id}" style="font-size:${n.r < 40 ? 12 : 15}px"></div>`;
        stage.appendChild(el);
    }
    for (const id in MAIN) addNode(id, MAIN[id]);

    const ph = document.createElement('div'); ph.id = 'phases'; stage.appendChild(ph);

    const E = {
        'sf-batt':   { d: 'M255,126 L336,193', col: AC.solar },
        'hm-batt':   { d: 'M465,126 L384,193', col: AC.solar },
        'batt-haus': { d: 'M360,284 L360,346', col: AC.batt },
        'netz-haus': { d: 'M156,400 L306,400', col: AC.grid }
    };
    const lineEl = {}, dotEl = {};
    function addEdge(k, d, col) {
        const p = document.createElementNS(NSc, 'path');
        p.setAttribute('d', d); linesG.appendChild(p); lineEl[k] = p;
        dotEl[k] = [0, 1].map(() => {
            const c = document.createElementNS(NSc, 'circle');
            c.setAttribute('r', 5); c.setAttribute('fill', col); c.style.display = 'none';
            dotsG.appendChild(c); return c;
        });
    }
    for (const k in E) addEdge(k, E[k].d, E[k].col);

    function buildGroups(list) {
        document.querySelectorAll('.grp-node').forEach(e => e.remove());
        Object.keys(lineEl).filter(k => k.startsWith('grp')).forEach(k => {
            lineEl[k].remove(); dotEl[k].forEach(d => d.remove());
            delete lineEl[k]; delete dotEl[k];
        });
        list.forEach((g, i) => {
            const p = gpos(i);
            addNode('r' + i, { x: p.x, y: p.y, r: RR, ic: g.icon || 'plug', icc: AC.room, lab: g.name || ('Gruppe ' + (i + 1)), lp: p.lp }, 'grp-node');
            let inner = `<div class="val">${fmt(g.value)}</div>`;
            if (g.daily) {
                inner += `<div class="sub" style="font-size:10px; line-height:1.25;">${g.daily}</div>`;
            }
            document.getElementById('body-r' + i).innerHTML = inner;
            const endY = p.lp === 'top' ? p.y + RR : p.y - RR;
            addEdge('grp' + i, `M412,400 L${p.x},400 L${p.x},${endY}`, AC.room);
        });
    }

    function arc(cx, cy, r, col, frac, off) {
        const C = 2 * Math.PI * r, seg = Math.max(frac * C, 0);
        const c = document.createElementNS(NSc, 'circle');
        c.setAttribute('cx', cx); c.setAttribute('cy', cy); c.setAttribute('r', r);
        c.setAttribute('fill', 'none'); c.setAttribute('stroke', col); c.setAttribute('stroke-width', 5);
        c.setAttribute('stroke-dasharray', `${Math.max(seg - 4, 0)} ${C - Math.max(seg - 4, 0)}`);
        c.setAttribute('stroke-dashoffset', -off * C);
        c.setAttribute('transform', `rotate(-90 ${cx} ${cy})`);
        ringG.appendChild(c);
    }
    function track(cx, cy, r) {
        const c = document.createElementNS(NSc, 'circle');
        c.setAttribute('cx', cx); c.setAttribute('cy', cy); c.setAttribute('r', r);
        c.setAttribute('fill', 'none'); c.setAttribute('stroke', 'var(--w-line)'); c.setAttribute('stroke-width', 5);
        ringG.appendChild(c);
    }
    function updateRings(segs, soc) {
        ringG.innerHTML = '';
        track(360, 400, 60);
        const tot = segs.reduce((a, s) => a + s[1], 0) || 1; let acc = 0;
        segs.forEach(([col, v]) => { if (v > 0) { arc(360, 400, 60, col, v / tot, acc / tot); acc += v; } });
        track(360, 232, 56);
        arc(360, 232, 56, AC.batt, (soc || 0) / 100, 0);
    }

    const CFG = [
        { sub: 'Sollwerte', fields: [
            { id: 'TargetImport', label: 'Ziel-Netzbezug', step: 1 },
            { id: 'ReserveHours', label: 'Reserve', step: 0.5 }
        ] },
        { sub: 'SOC-Schwellen', fields: [
            { id: 'FreeSoc', label: 'Free-SOC-Schwelle', step: 1 },
            { id: 'LowSocThreshold', label: 'Low-SOC-Schwelle', step: 1 },
            { id: 'LowSocOut', label: 'Low-SOC Fixausgabe', step: 1 },
            { id: 'MinSocShutdown', label: 'Min-SOC Abschaltung', step: 1 }
        ] },
        { sub: 'Morgenlogik', fields: [
            { id: 'MorningStart', label: 'Morgen Start', type: 'time' },
            { id: 'MorningEnd', label: 'Morgen Ende', type: 'time' }
        ] },
        { sub: 'PID-Regler', top: true, fields: [
            { id: 'Kp', label: 'Reaktionsstärke (Kp)', step: 0.01 },
            { id: 'Ki', label: 'Langzeit-Ausgleich (Ki)', step: 0.01 },
            { id: 'Kd', label: 'Dämpfung (Kd)', step: 0.01 }
        ] }
    ];
    function buildCfg() {
        let h = '';
        CFG.forEach(s => {
            h += `<div class="cfg-sub"${s.top ? ' style="margin-top:16px;border-top:0.5px solid var(--w-border);padding-top:12px"' : ''}>${s.sub}</div><div class="cfg-grid">`;
            s.fields.forEach(f => {
                h += `<div class="fld"><span>${f.label}</span><input class="ed" id="cfg-${f.id}" type="${f.type || 'number'}"${f.step ? ` step="${f.step}"` : ''} onchange="onCfg('${f.id}')"></div>`;
            });
            h += '</div>';
        });
        document.getElementById('cfg-body').innerHTML = h;
    }
    function onCfg(id) {
        const el = document.getElementById('cfg-' + id);
        if (el.type === 'time') { requestAction('Cfg' + id, el.value); return; }
        const v = parseFloat(el.value);
        if (isNaN(v)) return;
        requestAction('Cfg' + id, v);
    }
    buildCfg();

    let edgeState = {};
    function setState(d) {
        const l1 = d.l1 || 0, l2 = d.l2 || 0, l3 = d.l3 || 0;
        const grid = (d.grid !== undefined) ? d.grid : (l1 + l2 + l3);
        const imp = Math.max(grid, 0), exp = Math.max(-grid, 0);
        const battOut = d.battOut || 0;
        const battToHaus = Math.max(battOut - exp, 0);
        const haus = battToHaus + imp;

        document.getElementById('body-sf').innerHTML = `<div class="val">${fmt(d.solarflow)}</div>`;
        document.getElementById('body-hm').innerHTML = `<div class="val">${fmt(d.hoymiles)}</div>`;
        document.getElementById('body-batt').innerHTML =
            `<div class="sub" style="font-size:11px">${Math.round(d.soc || 0)}%</div>` +
            `<div class="val" style="color:${AC.batt}">${fmt(battOut)}</div>`;
        document.getElementById('body-netz').innerHTML = (grid >= 0)
            ? `<div class="val" style="color:${AC.grid}">&larr; ${fmt(imp)}</div>`
            : `<div class="val" style="color:${AC.batt}">&rarr; ${fmt(exp)}</div>`;
        ph.innerHTML = `L1 ${Math.round(l1)} &middot; L2 ${Math.round(l2)} &middot; L3 ${Math.round(l3)} W`;
        document.getElementById('body-haus').innerHTML = `<div class="val" style="font-size:17px">${fmt(haus)}</div>`;

        const groups = d.groups || [];
        buildGroups(groups);
        updateRings([[AC.batt, battToHaus], [AC.grid, imp]], d.soc);

        edgeState = {
            'sf-batt':   { w: d.solarflow || 0 },
            'hm-batt':   { w: d.hoymiles || 0 },
            'batt-haus': { w: battOut },
            'netz-haus': { w: Math.abs(grid), rev: grid < 0 }
        };
        groups.forEach((g, i) => { edgeState['grp' + i] = { w: g.value || 0 }; });
        for (const k in lineEl) {
            const on = edgeState[k] && edgeState[k].w > 0;
            dotEl[k].forEach(dt => dt.style.display = on ? 'block' : 'none');
        }

        const stats = d.stats || [];
        document.getElementById('stats-body').innerHTML = stats.map(s =>
            '<div class="lv"><span>' + s.label + '</span><b>' + s.value + '</b></div>').join('');
        document.getElementById('statsec').style.display = stats.length ? '' : 'none';

        const hasCfg = !!(d.hasConfig && d.config);
        document.getElementById('cfgsec').style.display = hasCfg ? '' : 'none';
        if (hasCfg) {
            CFG.forEach(s => s.fields.forEach(f => {
                const el = document.getElementById('cfg-' + f.id);
                const cv = d.config[f.id];
                if (el && document.activeElement !== el && cv !== undefined && cv !== null) el.value = cv;
            }));
            document.getElementById('cfg-out').textContent = fmt(battOut);
        }
        document.getElementById('cfg').style.display = (stats.length || hasCfg) ? '' : 'none';
    }

    // Pflicht-Funktion: empfängt Nachrichten vom Modul (UpdateVisualizationValue)
    function handleMessage(data) {
        const d = (typeof data === 'string') ? JSON.parse(data) : data;
        setState(d);
    }

    let p = 0, last = performance.now();
    function frame(now) {
        const dt = (now - last) / 1000; last = now; p = (p + dt * 0.28) % 1;
        for (const k in edgeState) {
            const st = edgeState[k];
            if (!st || st.w <= 0 || !lineEl[k]) continue;
            const path = lineEl[k], len = path.getTotalLength();
            dotEl[k].forEach((dt2, i) => {
                let t = (p + i / 2) % 1;
                if (st.rev) t = 1 - t;
                const pt = path.getPointAtLength(t * len);
                dt2.setAttribute('cx', pt.x); dt2.setAttribute('cy', pt.y);
            });
        }
        requestAnimationFrame(frame);
    }

    function fit() {
        const f = document.getElementById('fit');
        const k = f.clientWidth / 1080;
        stage.style.transform = 'scale(' + k + ')';
        f.style.height = (640 * k) + 'px';
    }
    new ResizeObserver(fit).observe(document.getElementById('fit'));
    window.addEventListener('resize', fit);
    fit();
    requestAnimationFrame(frame);
</script>

HTML;
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
