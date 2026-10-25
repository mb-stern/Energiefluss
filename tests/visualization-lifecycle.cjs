const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require('playwright');
const php = process.env.PHP_BINARY || 'php';
const fixture = (mode,id,state) => execFileSync(php,[path.join(__dirname,'ipsview-fixture.php'),mode,'',String(id),JSON.stringify(state)],{encoding:'utf8',maxBuffer:4*1024*1024});

(async () => {
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_BINARY || '/usr/bin/chromium',headless:true,args:['--no-sandbox']});
    try {
        for (const transport of ['native','browser']) {
            const context = await browser.newContext({viewport:{width:1600,height:1000}});
            const saved = new Map([[32101,{view:'flow',layout:'full'}],[32102,{view:'house',layout:'compact-wide'}]]);
            const errors = [];
            const page = await context.newPage();
            page.on('pageerror',error => errors.push(error.message));
            await context.addInitScript(() => {
                const NativeBlob = Blob;
                window.Blob = class extends NativeBlob {
                    constructor(parts,options) {
                        super(options?.type==='text/javascript' ? ['await new Promise(resolve=>setTimeout(resolve,100));',...parts] : parts,options);
                    }
                };
            });
            await page.exposeBinding('requestAction',({frame},action,value) => {
                assert.equal(action,'SaveAppViewState');
                saved.set(Number(frame.name().replace('tile-','')),JSON.parse(value));
            });
            const grid = '<style>iframe{width:750px;height:650px;border:0}#left,#right{display:inline-block;vertical-align:top}</style><div id="left"></div><div id="right"></div>';
            await context.route(/^https?:/,route => {
                const pathname = new URL(route.request().url()).pathname;
                if (pathname==='/icons.js') return route.fulfill({contentType:'text/javascript',body:''});
                if (pathname==='/grid') return route.fulfill({contentType:'text/html',body:grid});
                throw new Error('Unexpected network request: '+route.request().url());
            });
            async function mount() {
                if (transport==='browser') await page.goto('http://symcon-test.invalid/grid');
                else await page.setContent(grid);
                await page.evaluate(browserMode => {
                    window.previousDocuments = {};
                    window.previousCallbacks = {};
                    if (browserMode) {
                        localStorage.setItem('symcon-energiefluss-instance-32101-view','flow');
                        localStorage.setItem('symcon-energiefluss-instance-32101-technical-layout','full');
                        localStorage.setItem('symcon-energiefluss-instance-32102-view','house');
                        localStorage.setItem('symcon-energiefluss-instance-32102-technical-layout','compact-wide');
                    }
                },transport==='browser');
                for (const [id,state] of saved) {
                    await page.evaluate(({id,html}) => {
                        const element = document.createElement('iframe');
                        element.id=element.name='tile-'+id;
                        element.srcdoc=html;
                        document.getElementById(id===32101?'left':'right').appendChild(element);
                    },{id,html:fixture('fresh-tile',id,state)});
                }
            }
            async function ready(id,complete=true) {
                await page.waitForFunction(id=>typeof document.getElementById('tile-'+id)?.contentWindow?.handleMessage==='function',id);
                const element = await page.locator('#tile-'+id).elementHandle();
                const outer = await element.contentFrame();
                const data = JSON.parse(fixture('fresh',id,saved.get(id)));
                await outer.evaluate(data=>handleMessage(data),data);
                await outer.waitForFunction(()=>{const frame=document.getElementById('ef-bridge-frame');return frame&&!frame.classList.contains('ef-starting');});
                const inner = outer.childFrames()[0];
                if (complete) await inner.waitForFunction(()=>!!document.getElementById('eflow')&&!document.getElementById('ef-initial-view-guard'));
                return {outer,inner};
            }
            async function check(id) {
                const {inner}=await ready(id);
                assert.deepEqual(await inner.evaluate(()=>({view:currentDisplayMode,layout:currentTechnicalLayout,pvs:lastStateData.pvs.map(pv=>pv.value)})),{...saved.get(id),pvs:[2398,1971]});
            }
            await mount();
            await check(32101);await check(32102);
            for (let move=0;move<6;move++) {
                const id=move%2?32102:32101;
                const {outer}=await ready(id);
                await outer.evaluate(()=>handleMessage({command:'reloadHtml'}));
                await ready(id,false);
                await page.evaluate(({id,move})=>{
                    const tile=document.getElementById('tile-'+id),owner=tile.contentWindow;
                    const child=owner.document.getElementById('ef-bridge-frame').contentWindow;
                    previousDocuments[id]=owner.document;
                    previousCallbacks[id]=[owner.handleMessage,child.handleMessage,child.setState];
                    document.getElementById(move%2?'left':'right').appendChild(tile);
                },{id,move});
                await page.waitForFunction(id=>document.getElementById('tile-'+id).contentDocument!==previousDocuments[id],id);
                const late=JSON.parse(fixture('zero',id,saved.get(id)));
                await page.evaluate(({id,data})=>previousCallbacks[id].forEach(callback=>callback(data)),{id,data:late});
                await check(id);
                await check(id===32101?32102:32101);
            }
            const {outer,inner}=await ready(32101);
            await inner.evaluate(()=>dispatchEvent(new ErrorEvent('error',{message:'Live renderer error',error:new Error('Live renderer error')})));
            await outer.waitForFunction(()=>!document.getElementById('ef-load-retry').hidden);
            assert.match(await outer.locator('#ef-load-status').innerText(),/Live renderer error/);
            assert.equal(await outer.locator('#ef-load-status').isVisible(),true);
            await outer.locator('#ef-load-retry').click();
            await check(32101);await check(32102);
            await mount();
            await check(32101);await check(32102);
            assert.deepEqual(errors,[]);
            assert.deepEqual([...saved.values()],[{view:'flow',layout:'full'},{view:'house',layout:'compact-wide'}]);
            console.log('PASS: '+transport+', two isolated instances, DOM moves with pending imports, late callbacks, genuine error display, manual recovery and full reload');
            await context.close();
        }
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
