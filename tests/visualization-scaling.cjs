const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require('playwright');
const php = process.env.PHP_BINARY || 'php';
const fixture = (mode,id,state) => execFileSync(php,[path.join(__dirname,'ipsview-fixture.php'),mode,'',String(id),JSON.stringify(state)],{encoding:'utf8',maxBuffer:4*1024*1024});

(async () => {
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_BINARY || '/usr/bin/chromium',headless:true,args:['--no-sandbox']});
    try {
        const context = await browser.newContext({viewport:{width:425,height:557}});
        const errors = [], saved = new Map([[32101,{view:'flow',layout:'full'}],[32102,{view:'house',layout:'compact-wide'}]]);
        await context.route(/^https?:/,route=>route.fulfill({contentType:'text/javascript',body:''}));
        async function open(id) {
            const page = await context.newPage();
            page.on('pageerror',error=>errors.push(error.message));
            await page.exposeBinding('requestAction',(_,action,value)=>{
                assert.equal(action,'SaveAppViewState');saved.set(id,JSON.parse(value));
            });
            await page.setContent(fixture('fresh-tile',id,saved.get(id)));
            await page.waitForFunction(()=>{const frame=document.getElementById('ef-bridge-frame');return frame&&!frame.classList.contains('ef-starting');});
            const frame=page.frames().find(frame=>frame!==page.mainFrame()&&frame.url()==='about:srcdoc');
            await frame.waitForFunction(()=>!document.getElementById('ef-initial-view-guard'));
            return {page,frame};
        }
        const {page,frame}=await open(32101), second=await open(32102);
        async function settle() {
            await frame.evaluate(async()=>{
                await document.querySelector('sunsynk-power-flow-card').updateComplete;
                await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
            });
        }
        async function measure() {
            return frame.evaluate(()=>{
                const host=document.getElementById('scale-host').getBoundingClientRect();
                const svg=document.querySelector('sunsynk-power-flow-card').shadowRoot.querySelector('svg');
                const view=svg.viewBox.baseVal, matrix=svg.getScreenCTM();
                const left=view.x*matrix.a+matrix.e,top=view.y*matrix.d+matrix.f;
                return {availableWidth:host.width,availableHeight:host.height,hostLeft:host.left,hostTop:host.top,width:view.width,height:view.height,scale:matrix.a,left,top,right:left+view.width*matrix.a,bottom:top+view.height*matrix.d,layout:currentTechnicalLayout,view:currentDisplayMode,wide:document.querySelector('sunsynk-power-flow-card')._config.wide};
            });
        }
        function fits(size) {
            const optimal=Math.min(size.availableWidth/size.width,size.availableHeight/size.height);
            assert.ok(Math.abs(size.scale-optimal)<0.005,'SVG must use the available area without a second height constraint');
            assert.ok(size.left>=size.hostLeft-1 && size.top>=size.hostTop-1 && size.right<=size.hostLeft+size.availableWidth+1 && size.bottom<=size.hostTop+size.availableHeight+1,'Complete SVG must remain inside the available area');
        }
        for (const layout of ['full','full-wide','compact-wide','compact','lite','lite-wide']) {
            if (await frame.evaluate(()=>currentTechnicalLayout.endsWith('-wide'))!==layout.endsWith('-wide')) await frame.locator('#technical-wide-button').click();
            for (let step=0;step<3 && await frame.evaluate(()=>currentTechnicalLayout)!==layout;step++) await frame.locator('#technical-layout-button').click();
            await frame.waitForFunction(layout=>currentTechnicalLayout===layout,layout);
            let tall;
            for (const viewport of [{width:425,height:557},{width:425,height:425},{width:425,height:300},{width:717,height:563},{width:1000,height:300},{width:1280,height:900}]) {
                await page.setViewportSize(viewport);await settle();
                const size=await measure();fits(size);
                assert.equal(size.layout,layout);assert.equal(size.view,'flow');assert.equal(size.wide,layout.endsWith('-wide'));
                if (viewport.height===557) tall=size;
                if (viewport.height===425 && tall.availableWidth*tall.height/tall.width<=size.availableHeight) {
                    assert.ok(Math.abs(size.scale-tall.scale)<0.005,'Reducing height must not shrink the diagram while it still fits');
                }
            }
            assert.deepEqual(await second.frame.evaluate(()=>({view:currentDisplayMode,layout:currentTechnicalLayout})),{view:'house',layout:'compact-wide'});
            console.log('PASS: '+layout+', full SVG area, height-only resize, landscape and maximized size, independent second instance');
        }
        for (const largeFont of [false,true]) {
            await frame.evaluate(largeFont=>{const card=document.querySelector('sunsynk-power-flow-card');card.setConfig({...card._config,large_font:largeFont});},largeFont);
            await settle();fits(await measure());
            assert.equal(await frame.evaluate(()=>document.querySelector('sunsynk-power-flow-card')._config.large_font),largeFont);
        }
        await frame.locator('#technical-wide-button').click();
        await frame.waitForFunction(()=>currentTechnicalLayout==='lite');
        const changed=JSON.parse(fixture('fresh',32101,saved.get(32101)));
        changed.pvs=Array.from({length:6},(_,index)=>({...changed.pvs[index%2],name:'PV '+(index+1)}));
        await page.evaluate(data=>handleMessage(data),changed);
        await frame.waitForFunction(()=>lastStateData.pvs.length===6);
        await settle();fits(await measure());
        console.log('PASS: normal and large fonts, viewBox changes after layout and live device configuration updates');
        await frame.locator('#display-mode-button').click();
        await settle();
        const house=await frame.evaluate(()=>{
            const root=document.getElementById('scale-root').getBoundingClientRect(),host=document.getElementById('scale-host').getBoundingClientRect();
            return {view:currentDisplayMode,ratio:root.width/root.height,inside:root.left>=host.left-1&&root.top>=host.top-1&&root.right<=host.right+1&&root.bottom<=host.bottom+1};
        });
        assert.equal(house.view,'house');assert.ok(Math.abs(house.ratio-900/640)<0.001&&house.inside);
        await frame.locator('#display-mode-button').click();await settle();fits(await measure());
        assert.deepEqual(saved.get(32102),{view:'house',layout:'compact-wide'});
        assert.deepEqual(errors,[]);
        console.log('PASS: switching between house and technical views restores the correct aspect ratio without changing instance preferences');
        await context.close();
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
