const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/js/app.js','utf8').split('// Warn only when editable form values differ from their original values.')[1].split('// Profile links support keyboard navigation')[0];
function setup(withIndicator=true) {
    const handlers = {}, documentHandlers = {}, windowHandlers = {};
    const classes = new Set();
    const indicator = {textContent:'', classList:{toggle:(name,on)=>on?classes.add(name):classes.delete(name)}};
    const form = {values:[['name','Original']],querySelector:()=>withIndicator?indicator:null,addEventListener:(type,fn)=>handlers[type]=fn};
    const window = {location:{href:'http://localhost/form',pathname:'/form',search:''},confirm:()=>false,addEventListener:(type,fn)=>windowHandlers[type]=fn};
    const context={window,document:{querySelectorAll:()=>[form],addEventListener:(type,fn)=>documentHandlers[type]=fn},FormData:class {constructor(f){this.f=f;}entries(){return this.f.values.values();}},URL,Map,Array,JSON};
    vm.runInNewContext(source,context);
    return {form,indicator,handlers,windowHandlers,documentHandlers,window};
}
test('only changed values warn; restoring values clears the warning',()=>{
    const app=setup(); let warned=false;
    app.windowHandlers.beforeunload({preventDefault(){warned=true;}});assert.equal(warned,false);
    app.form.values=[['name','Changed']];app.handlers.input();assert.equal(app.indicator.textContent,'Unsaved changes');
    app.windowHandlers.beforeunload({preventDefault(){warned=true;}});assert.equal(warned,true);
    app.form.values=[['name','Original']];app.handlers.change();assert.equal(app.indicator.textContent,'No unsaved changes');
});
test('saving bypasses navigation warning',()=>{
    const app=setup();app.form.values=[['name','Changed']];app.handlers.input();app.handlers.submit();
    let warned=false;app.windowHandlers.beforeunload({preventDefault(){warned=true;}});assert.equal(warned,false);
});
test('cancelled navigation retains dirty data and same-page anchors stay available',()=>{
    const app=setup();app.form.values=[['name','Changed']];app.handlers.input();let blocked=false;
    const link={href:'http://localhost/other',target:'',hasAttribute:()=>false};
    app.documentHandlers.click({target:{closest:()=>link},preventDefault(){blocked=true;}});assert.equal(blocked,true);
    blocked=false;link.href='http://localhost/form#section';app.documentHandlers.click({target:{closest:()=>link},preventDefault(){blocked=true;}});assert.equal(blocked,false);
});

test('dirty protection works without adding a visible status label',()=>{
    const app=setup(false);app.form.values=[['name','Changed']];app.handlers.input();
    let warned=false;app.windowHandlers.beforeunload({preventDefault(){warned=true;}});assert.equal(warned,true);
});
