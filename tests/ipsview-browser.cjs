const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require('playwright');
const php = process.env.PHP_BINARY || 'php';
const fixture = (mode, token = '', id = 32101, state = {view:'flow',layout:'full'}) =>
    execFileSync(php, [path.join(__dirname, 'ipsview-fixture.php'), mode, token, String(id), JSON.stringify(state)], {encoding:'utf8',maxBuffer:4*1024*1024});

(async () => {
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_BINARY || '/usr/bin/chromium',headless:true,args:['--no-sandbox']});
    try {
        for (const transport of ['native','mobile','browser','connect']) {
            const native = transport==='native' || transport==='mobile';
            const context = await browser.newContext(transport==='mobile' ? {viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:3} : {viewport:{width:1250,height:750}});
            const saved = new Map([[32101,{view:'flow',layout:'full'}],[32102,{view:'house',layout:'compact-wide'}]]);
            const pages = new Map(), requests = [], errors = [];
            let responseMode = 'fresh';
            await context.addInitScript(({native}) => {
                if (window === window.parent) {
                    window.frameStartupSamples = [];
                    new MutationObserver(records => {
                        for (const {target} of records) {
                            if (target.id !== 'ef-bridge-frame' || !target.srcdoc) continue;
                            const style = getComputedStyle(target), rect = target.getBoundingClientRect();
                            window.frameStartupSamples.push({display:style.display,opacity:style.opacity,width:rect.width,height:rect.height});
                        }
                    }).observe(document,{subtree:true,attributes:true,attributeFilter:['srcdoc']});
                }
                if (window !== window.parent) {
                    if (native) Object.defineProperty(window,'localStorage',{configurable:true,get(){throw new DOMException('blocked','SecurityError');}});
                    document.addEventListener('DOMContentLoaded', () => {
                        if (!document.getElementById('eflow')) return;
                        window.visibleSamples = [];
                        const sample = () => {
                            if (!document.getElementById('ef-initial-view-guard')) {
                                const hass = document.querySelector('sunsynk-power-flow-card')?.hass;
                                window.visibleSamples.push({view:currentDisplayMode,layout:currentTechnicalLayout,pvs:lastStateData?.pvs.length,batteries:lastStateData?.batteries.length,pv1:hass?.states['sensor.symcon_pv1']?.state});
                            }
                            requestAnimationFrame(sample);
                        };
                        requestAnimationFrame(sample);
                    });
                } else if (!native && /^https?:$/.test(window.location.protocol)) {
                    localStorage.setItem('symcon-energiefluss-instance-32101-view','flow');
                    localStorage.setItem('symcon-energiefluss-instance-32101-technical-layout','full');
                    localStorage.setItem('symcon-energiefluss-instance-32102-view','house');
                    localStorage.setItem('symcon-energiefluss-instance-32102-technical-layout','compact-wide');
                }
            }, {native});
            await context.route(/^https?:/, route => {
                const url = new URL(route.request().url());
                requests.push(url.href);
                if (url.pathname === '/icons.js') return route.fulfill({contentType:'text/javascript',body:''});
                if (url.pathname.startsWith('/view/')) {
                    const id = Number(url.pathname.slice(6));
                    const tile = fixture('fresh-tile','',id,saved.get(id));
                    return route.fulfill({contentType:'text/html',body:'<style>:root{--content-color:#eeeeee}</style>'+tile});
                }
                throw new Error('Unexpected network request: '+url);
            });
            async function respond(page, id, token, mode = responseMode) {
                const data = JSON.parse(fixture(mode,token,id,saved.get(id)));
                await page.evaluate(data => handleMessage(data), data);
                return data;
            }
            async function open(id) {
                const page = await context.newPage();
                pages.set(page,id);
                page.on('pageerror', error => errors.push(error.message));
                await page.exposeBinding('requestAction', async ({page:source}, action, value) => {
                    const instance = pages.get(source);
                    if (action === 'SaveAppViewState') {saved.set(instance,JSON.parse(value)); return;}
                    throw new Error('Startup must not require a requestAction: '+action);
                });
                if (native) await page.setContent('<style>:root{--content-color:#eeeeee}</style>'+fixture('fresh-tile','',id,saved.get(id)));
                else await page.goto((transport==='connect'?'https://example.ipmagic.de':'http://symcon-test.invalid')+'/view/'+id);
                return page;
            }
            async function rendered(page) {
                await page.waitForFunction(() => {const frame=document.getElementById('ef-bridge-frame');return frame && !frame.classList.contains('ef-starting');});
                const frame = page.frames().find(frame => frame !== page.mainFrame() && frame.url()==='about:srcdoc');
                await frame.waitForFunction(() => !!document.getElementById('eflow') && !document.getElementById('ef-initial-view-guard'));
                return frame;
            }
            const first = await open(32101);
            await first.waitForFunction(() => typeof handleMessage === 'function');
            await rendered(first);
            assert.equal(await first.locator('#ef-bridge-frame').isVisible(),true);
            let frame = await rendered(first);
            const actual = await frame.evaluate(() => ({view:currentDisplayMode,layout:currentTechnicalLayout,pvs:lastStateData.pvs.map(pv=>pv.value),battery:lastStateData.batteries[0].value,theme:document.documentElement.dataset.theme,injected:window.injected}));
            assert.deepEqual(actual,{view:'flow',layout:'full',pvs:[2398,1971],battery:2457,theme:'dark',injected:undefined});
            await frame.waitForFunction(() => visibleSamples.length > 0);
            for (const sample of await frame.evaluate(() => visibleSamples)) {assert.equal(sample.pvs,2);assert.equal(sample.batteries,1);assert.equal(sample.layout,'full');assert.equal(Number(sample.pv1),2398);}
            if (native) assert.ok(await frame.locator('[data-symcon-icon="charging-station"]').count());
            await first.evaluate(data=>handleMessage(data),{stateVersion:1,pvs:[],batteries:[],groups:[],viewState:{view:'house',layout:'lite'}});
            assert.equal(await frame.evaluate(()=>lastStateData.pvs.length),2);
            const startupSamples = await first.evaluate(()=>frameStartupSamples);
            assert.ok(startupSamples.length>0);
            for (const sample of startupSamples) {
                assert.notEqual(sample.display,'none','WebView must be able to initialize the frame while loading');
                assert.ok(sample.width>0 && sample.height>0);
                assert.equal(sample.opacity,'0','Unconfigured card must remain invisible while loading');
            }
            await first.evaluate(()=>dispatchEvent(new PageTransitionEvent('pagehide',{persisted:true})));
            await first.evaluate(()=>dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));
            await first.evaluate(()=>handleMessage({command:'reloadHtml'}));
            frame = await rendered(first);
            assert.equal(await frame.locator('sunsynk-power-flow-card').count(),1,'Returning to cached tile must retain local modules');
            assert.equal(await frame.evaluate(()=>Number(document.querySelector('sunsynk-power-flow-card').hass.states['sensor.symcon_pv1'].state)),2398);
            const second = await open(32102);
            const secondFrame = await rendered(second);
            assert.deepEqual(await secondFrame.evaluate(()=>({view:currentDisplayMode,layout:currentTechnicalLayout})),{view:'house',layout:'compact-wide'});
            await frame.locator('#display-mode-button').click();
            await frame.waitForFunction(()=>!!document.querySelector('power-flow-card') && getComputedStyle(document.querySelector('power-flow-card')).opacity==='1');
            for (let reopen=0;reopen<3;reopen++) {
                await first.evaluate(()=>handleMessage({command:'reloadHtml'}));
                frame=await rendered(first);
                assert.equal(await frame.evaluate(()=>currentDisplayMode),'house');
            }
            assert.equal(await secondFrame.evaluate(()=>currentTechnicalLayout),'compact-wide');
            responseMode='zero';
            await first.evaluate(()=>handleMessage({command:'reloadHtml'}));
            frame=await rendered(first);
            await respond(first,32101,'');
            await frame.waitForFunction(()=>lastStateData.pvs[0].value===0);
            assert.deepEqual(await frame.evaluate(()=>lastStateData.pvs.map(pv=>pv.value)),[0,0]);
            responseMode='label';
            await first.evaluate(()=>handleMessage({command:'reloadHtml'}));
            frame=await rendered(first);
            await respond(first,32101,'');
            await frame.waitForFunction(()=>lastStateData.pvs[0].name.startsWith('</script>'));
            assert.equal(await frame.evaluate(()=>window.injected),undefined);
            assert.equal(await frame.evaluate(()=>lastStateData.pvs[0].name),'</script><script>window.injected=true</script>$&$$');
            await first.evaluate(()=>handleMessage({command:'reloadHtml'}));
            frame=await rendered(first);
            assert.equal(await first.locator('#ef-load-status').isVisible(),false);
            assert.equal(await frame.evaluate(()=>currentDisplayMode),'house');
            responseMode='fresh';
            await first.evaluate(()=>handleMessage({command:'reloadHtml'}));await rendered(first);
            if(native) assert.equal(requests.length,0);
            assert.ok(requests.every(url=>/\/view\/|\/icons\.js$/.test(url)));
            assert.deepEqual(errors,[]);
            if (native) {
                await context.addInitScript(()=>{window.DecompressionStream=undefined;});
                const legacy = await open(32101);
                await legacy.waitForFunction(()=>!document.getElementById('ef-load-retry').hidden);
                assert.match(await legacy.locator('#ef-load-status').innerText(),/gzip/);
            }
            console.log('PASS: '+transport+' without WebHook, immediate embedded state without startup requestAction, both original cards, isolated views, reloads after page return, zeros, escaped labels and live SDK updates');
            await context.close();
        }
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
