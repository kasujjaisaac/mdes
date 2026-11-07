import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync('resources/js/app.js', 'utf8').split('// Keep unfinished statistical sections')[1];
function setup(storage = new Map()) {
 const input = {name:'data[headteacher_name]',value:'Original',disabled:false,type:'text'};
 const token = {name:'_token',value:'test'};
 const revision = {name:'draft_revision',value:'0'};
 const handlers = {}, docs = {}, wins = {}, timers = new Map(); let tick=0;
 const indicator={textContent:'',dataset:{},after(){}}; const error={hidden:true,textContent:'',scrollIntoView(){},focus(){}}; const submissions=[]; let dispatching=false;
 const state={initial:'',dirty:false,submitting:false};
 const form={dataset:{autosaveUrl:'/draft',autosaveKey:'1-1-identity'},elements:[input,token,revision],querySelector(s){return s==='[data-form-action-error]'?error:null},requestSubmit(button){if(!dispatching)submissions.push(button)},querySelectorAll(s){return s==='[data-autosave-status]'?[indicator]:[]},addEventListener(e,f){handlers[e]=f},dispatchEvent(e){handlers[e.type]?.(e)}};
 form.elements._token=token;form.elements.draft_revision=revision;
 class Data {constructor(f){this.rows=f?f.elements.filter(x=>!x.disabled).map(x=>[x.name,x.value]):[]}entries(){return this.rows.values()}set(k,v){this.rows=this.rows.filter(([n])=>n!==k);this.rows.push([k,String(v)])}append(k,v){this.rows.push([k,v])}}
 const calls=[]; let respond=async()=>({ok:true,json:async()=>({revision:Number(revision.value)+1,valid:true})});
 const context={AbortController,protectedForms:new Map([[form,state]]),formFingerprint:()=>input.value,document:{visibilityState:'hidden',querySelectorAll:()=>[form],addEventListener(e,f){docs[e]=f}},window:{addEventListener(e,f){wins[e]=f}},FormData:Data,HTMLFormElement:class{static [Symbol.hasInstance](obj){return obj===form}},localStorage:{getItem:k=>storage.get(k)||null,setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},setTimeout(f,delay){if(delay===0){queueMicrotask(f);return ++tick}timers.set(++tick,f);return tick},clearTimeout(k){timers.delete(k)},fetch:async(url,opts)=>{calls.push(opts.body.rows);return respond()},Event:class{constructor(type){this.type=type}},location:{href:'http://local/form',origin:'http://local',pathname:'/form',search:''},console};
 vm.runInNewContext('// Keep unfinished statistical sections'+source,context);
 return {submit(event){dispatching=true;const result=docs.submit(event);dispatching=false;return result},form,error,submissions,input,revision,handlers,docs,wins,state,indicator,storage,calls,timers,setResponse:f=>respond=f,flush:async()=>{for(let i=0;i<12;i++)await Promise.resolve()}};
}
let env=setup();env.input.value='Typed';env.handlers.input();assert.equal(env.storage.size,1);env.docs.visibilitychange();await env.flush();assert.equal(env.revision.value,1);assert.equal(env.storage.size,0);assert.equal(env.indicator.textContent,'Saved automatically');
let resolve;env.setResponse(()=>new Promise(r=>resolve=r));env.input.value='First';env.handlers.input();env.docs.visibilitychange();env.input.value='Newer';env.handlers.input();resolve({ok:true,json:async()=>({revision:2,valid:true})});await env.flush();assert.equal(env.storage.size,1);assert.match([...env.storage.values()][0],/Newer/);env.setResponse(async()=>({ok:true,json:async()=>({revision:3,valid:true})}));env.docs.visibilitychange();await env.flush();assert.equal(env.storage.size,0);assert.equal(env.revision.value,3);
env=setup();env.input.value='Offline progress';env.handlers.input();env.setResponse(async()=>{throw new Error('offline')});env.docs.visibilitychange();await env.flush();assert.equal(env.storage.size,1);let recovered=setup(env.storage);assert.equal(recovered.input.value,'Offline progress');
env=setup();env.input.value='Conflicting';env.handlers.input();env.setResponse(async()=>({ok:false,status:409}));env.docs.visibilitychange();await env.flush();assert.equal(env.storage.size,1);assert.match(env.indicator.textContent,/another tab/);let before=env.calls.length;env.docs.visibilitychange();await env.flush();assert.equal(env.calls.length,before);
console.log('Autosave browser-logic checks passed: persistence, in-flight edits, offline restoration, and conflict protection.');


const button={textContent:'Next',name:'navigate',value:'next',setAttribute(){},removeAttribute(){}};
let clean=setup();await clean.submit({target:clean.form,submitter:button,defaultPrevented:false,preventDefault(){this.defaultPrevented=true}});assert.equal(clean.submissions.length,1);assert.equal(clean.submissions[0].value,'next');assert.equal(button.textContent,'Next');
await env.submit({target:env.form,submitter:button,defaultPrevented:false,preventDefault(){this.defaultPrevented=true}});assert.equal(env.error.hidden,false);assert.match(env.error.textContent,/another tab/);assert.equal(env.submissions.length,0);assert.equal(button.textContent,'Next');
console.log('Next-button checks passed: navigation retains its value and blocked navigation displays a visible error.');

let implicit=setup();await implicit.submit({target:implicit.form,submitter:null,defaultPrevented:false,preventDefault(){this.defaultPrevented=true}});assert.equal(implicit.submissions.length,1);
console.log('Native-event re-entry regression checks passed for both Next and implicit submission.');
