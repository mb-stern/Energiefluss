const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require('playwright');
const php = process.env.PHP_BINARY || 'php';
const fixture = (mode,id,state) => execFileSync(php,[path.join(__dirname,'ipsview-fixture.php'),mode,'',String(id),JSON.stringify(state)],{encoding:'utf8',maxBuffer:4*1024*1024});

(async () => {
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_BINARY || '/usr/bin/chromium',headless:true,args:['--no-sandbox']});
    try {
        const context=await browser.newContext({viewport:{width:1000,height:750}});
        const saved=new Map([[32101,{view:'flow',layout:'full'}],[32102,{view:'house',layout:'compact-wide'}]]), errors=[];
        await context.route(/^https?:/,route=>route.fulfill({contentType:'text/javascript',body:''}));
        async function open(id,disabled) {
            const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
            await page.exposeBinding('requestAction',(_,action,value)=>{assert.equal(action,'SaveAppViewState');saved.set(id,JSON.parse(value));});
            await page.setContent(fixture(disabled?'no-flow-tile':'fresh-tile',id,saved.get(id)));
            await page.waitForFunction(()=>{const element=document.getElementById('ef-bridge-frame');return element&&!element.classList.contains('ef-starting');});
            const frame=page.frames().find(frame=>frame!==page.mainFrame()&&frame.url()==='about:srcdoc');
            await frame.waitForFunction(()=>initialCardsReady.has('flow')&&initialCardsReady.has('house'));
            return {page,frame,id};
        }
        const enabled=await open(32101,false),disabled=await open(32102,true);
        async function update(target,off,legacy=false) {
            const data=JSON.parse(fixture(off?'no-flow':'fresh',target.id,saved.get(target.id)));
            if (legacy) delete data.showFlowPoints;
            await target.page.evaluate(data=>handleMessage(data),data);
            await target.frame.waitForFunction(off=>document.querySelector('sunsynk-power-flow-card')._config.disable_animations===off,off&&!legacy);
            await settle(target);
        }
        async function settle(target) {
            await target.frame.evaluate(async()=>{
                await document.querySelector('sunsynk-power-flow-card').updateComplete;
                await document.querySelector('power-flow-card').updateComplete;
                await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
            });
        }
        async function dots(target,off) {
            await settle(target);
            const actual=await target.frame.evaluate(()=>{
                const card=document.querySelector('sunsynk-power-flow-card');
                return {disabled:card._config.disable_animations,moving:card.shadowRoot.querySelectorAll('animateMotion').length,paths:card.shadowRoot.querySelectorAll('path').length,pv:Number(card.hass.states['sensor.symcon_pv1'].state),loop:flowAnimationFrame};
            });
            assert.equal(actual.disabled,off);assert.ok(off?actual.moving===0:actual.moving>0,'The native option must remove only the moving flow dots');
            assert.ok(actual.paths>0);assert.equal(actual.pv,2398);
            assert.ok(off?actual.loop===0:actual.loop>0,'The module must stop its own flow animation loop when disabled');
        }
        async function layout(target,value) {
            if (await target.frame.evaluate(()=>currentDisplayMode)!=='flow') await target.frame.locator('#display-mode-button').click();
            if (await target.frame.evaluate(()=>currentTechnicalLayout.endsWith('-wide'))!==value.endsWith('-wide')) await target.frame.locator('#technical-wide-button').click();
            for (let step=0;step<3&&await target.frame.evaluate(()=>currentTechnicalLayout)!==value;step++) await target.frame.locator('#technical-layout-button').click();
            await target.frame.waitForFunction(value=>currentTechnicalLayout===value,value);
        }
        await dots(enabled,false);await dots(disabled,true);
        for (const value of ['full','full-wide','compact-wide','compact','lite','lite-wide']) {
            await layout(enabled,value);await layout(disabled,value);
            await dots(enabled,false);await dots(disabled,true);
        }
        await update(enabled,true);await dots(enabled,true);await dots(disabled,true);
        if (await enabled.frame.evaluate(()=>currentDisplayMode)!=='house') await enabled.frame.locator('#display-mode-button').click();
        await settle(enabled);
        const stopped=await enabled.frame.evaluate(()=>{
            const card=document.querySelector('power-flow-card'),lines=[...card.shadowRoot.querySelectorAll('.anim-line')];
            return {flag:card.hasAttribute('data-flow-points-disabled'),lines:lines.length,stopped:lines.every(line=>getComputedStyle(line).animationName==='none'&&getComputedStyle(line).opacity==='0'),background:card.shadowRoot.querySelectorAll('#svg-container-bg path').length};
        });
        assert.ok(stopped.flag&&stopped.lines>0&&stopped.stopped&&stopped.background>0,'House view must stop animated pulses while retaining its static diagram');
        await update(enabled,false);await dots(enabled,false);
        await enabled.frame.waitForFunction(()=>[...document.querySelector('power-flow-card').shadowRoot.querySelectorAll('.anim-line')].some(line=>getComputedStyle(line).animationName!=='none'&&getComputedStyle(line).animationPlayState==='running'));
        await update(enabled,true);await dots(enabled,true);
        await update(enabled,false,true);await dots(enabled,false);
        const next=JSON.parse(fixture('no-flow',disabled.id,saved.get(disabled.id)));next.pvs[0].value=1234;
        await disabled.page.evaluate(data=>handleMessage(data),next);
        await disabled.frame.waitForFunction(()=>Number(document.querySelector('sunsynk-power-flow-card').hass.states['sensor.symcon_pv1'].state)===1234);
        assert.equal(await disabled.frame.evaluate(()=>document.querySelector('sunsynk-power-flow-card').shadowRoot.querySelectorAll('animateMotion').length),0);
        assert.deepEqual(errors,[]);
        console.log('PASS: Sunsynk 7.4.2, native flow dots on/off in all six layouts, static paths and live values, default compatibility, house pulses, stopped animation loop and independent instances');
        await context.close();
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
