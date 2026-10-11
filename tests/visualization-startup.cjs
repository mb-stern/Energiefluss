const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require('playwright');
const php = process.env.PHP_BINARY || 'php';
const fixture = mode => execFileSync(php, [path.join(__dirname,'ipsview-fixture.php'),mode,'','32101','{"view":"flow","layout":"full"}'], {encoding:'utf8',maxBuffer:4*1024*1024});

(async () => {
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_BINARY || '/usr/bin/chromium',headless:true,args:['--no-sandbox']});
    try {
        for (const scenario of ['document-loading','tile-without-size','slow-unpack']) {
            const context = await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
            const errors = [], requests = [];
            let finishDocument;
            const pendingDocument = new Promise(resolve => {finishDocument = resolve;});
            const tile = fixture('fresh-tile');
            await context.route(/^https?:/, async route => {
                const pathname = new URL(route.request().url()).pathname;
                requests.push(pathname);
                if (pathname==='/icons.js') return route.fulfill({contentType:'text/javascript',body:''});
                if (pathname==='/view') return route.fulfill({contentType:'text/html',body:tile+'<img src="/finish.svg" hidden>'});
                if (pathname==='/finish.svg') {await pendingDocument;return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>'});}
                throw new Error('Unexpected network request: '+route.request().url());
            });
            await context.addInitScript(scenario => {
                if (window !== window.parent) return;
                if (/^https?:$/.test(location.protocol)) {
                    localStorage.setItem('symcon-energiefluss-instance-32101-view','flow');
                    localStorage.setItem('symcon-energiefluss-instance-32101-technical-layout','full');
                }
                window.mountSamples = [];
                window.bundlePrepared = false;
                const descriptor = Object.getOwnPropertyDescriptor(HTMLIFrameElement.prototype,'srcdoc');
                Object.defineProperty(HTMLIFrameElement.prototype,'srcdoc',{
                    ...descriptor,
                    set(value) {
                        if (this.id==='ef-bridge-frame' && value) {
                            const bounds = this.getBoundingClientRect();
                            window.mountSamples.push({state:document.readyState,width:bounds.width,height:bounds.height});
                        }
                        descriptor.set.call(this,value);
                    }
                });
                const json = Response.prototype.json;
                Response.prototype.json = async function () {
                    const result = await json.call(this);
                    window.bundlePrepared = true;
                    if (scenario==='slow-unpack') await new Promise(resolve => {window.releaseBundle = resolve;});
                    return result;
                };
            },scenario);
            const page = await context.newPage();
            page.on('pageerror',error => errors.push(error.message));
            await page.exposeBinding('requestAction',() => {throw new Error('Startup must not require an SDK action');});
            await page.clock.install();
            let navigation;
            if (scenario==='document-loading') {navigation = page.goto('http://symcon-test.invalid/view',{waitUntil:'load'});navigation.catch(()=>{});await page.waitForFunction(()=>document.readyState==='interactive' && typeof handleMessage==='function');}
            else await page.setContent((scenario==='tile-without-size'?'<style id="pending-size">#ef-bridge-frame{width:0!important;height:0!important}</style>':'')+tile);
            if (scenario==='slow-unpack') await page.waitForFunction(()=>bundlePrepared===true);
            else await page.waitForFunction(()=>typeof handleMessage==='function');
            await page.clock.runFor(20000);
            assert.equal(await page.evaluate(()=>mountSamples.length),0,'Inner page must wait for outer tile readiness');
            if (scenario==='document-loading') assert.equal(await page.locator('#ef-bridge-frame').count(),0,'Inner frame must not block completion of outer tile loading');
            assert.equal(await page.locator('#ef-load-retry').isVisible(),false,'Preparing the outer tile must not consume the inner page timeout');
            const live = JSON.parse(fixture('zero'));
            await page.evaluate(data=>handleMessage(data),live);
            if (scenario==='document-loading') {finishDocument();await navigation;}
            if (scenario==='tile-without-size') await page.evaluate(()=>document.getElementById('pending-size').remove());
            if (scenario==='slow-unpack') await page.evaluate(()=>releaseBundle());
            await page.waitForFunction(()=>{const frame=document.getElementById('ef-bridge-frame');return frame && !frame.classList.contains('ef-starting');});
            const frame = page.frames().find(frame=>frame!==page.mainFrame() && frame.url()==='about:srcdoc');
            await frame.waitForFunction(()=>!document.getElementById('ef-initial-view-guard'));
            assert.deepEqual(await frame.evaluate(()=>({view:currentDisplayMode,layout:currentTechnicalLayout,pvs:lastStateData.pvs.map(pv=>pv.value)})),{view:'flow',layout:'full',pvs:[0,0]});
            const samples = await page.evaluate(()=>mountSamples);
            assert.equal(samples.length,1,'There must be exactly one start, with no automatic restart');
            assert.equal(samples[0].state,'complete');
            assert.ok(samples[0].width>0 && samples[0].height>0);
            assert.equal(await page.locator('#ef-load-retry').isVisible(),false);
            assert.deepEqual(errors,[]);
            assert.ok(requests.every(url=>['/view','/finish.svg','/icons.js'].includes(url)));
            console.log('PASS: '+scenario+', wait for tile readiness, one start, saved view and newest SDK data');
            await context.close();
        }
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
