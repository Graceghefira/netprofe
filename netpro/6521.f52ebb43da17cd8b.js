"use strict";(self.webpackChunkfuse=self.webpackChunkfuse||[]).push([[6521],{6521:(P,a,r)=>{r.r(a),r.d(a,{ion_input_password_toggle:()=>n});var i=r(54261),d=r(74929),c=r(80333),u=r(23992),p=r(9483);const n=(()=>{let l=class{constructor(e){(0,i.r)(this,e),this.togglePasswordVisibility=()=>{const{inputElRef:s}=this;s&&(s.type="text"===s.type?"password":"text")},this.color=void 0,this.showIcon=void 0,this.hideIcon=void 0,this.type="password"}onTypeChange(e){"text"===e||"password"===e||(0,d.p)(`ion-input-password-toggle only supports inputs of type "text" or "password". Input of type "${e}" is not compatible.`,this.el)}connectedCallback(){const{el:e}=this,s=this.inputElRef=e.closest("ion-input");s?this.type=s.type:(0,d.p)("No ancestor ion-input found for ion-input-password-toggle. This component must be slotted inside of an ion-input.",e)}disconnectedCallback(){this.inputElRef=null}render(){var e,s;const{color:h,type:b}=this,g=(0,p.b)(this),E=null!==(e=this.showIcon)&&void 0!==e?e:u.x,I=null!==(s=this.hideIcon)&&void 0!==s?s:u.y,y="text"===b;return(0,i.h)(i.f,{key:"d9811e25bfeb2aa197352bb9be852e9e420739d5",class:(0,c.c)(h,{[g]:!0})},(0,i.h)("ion-button",{key:"1eaea1442b248fb2b8d61538b27274e647a07804",mode:g,color:h,fill:"clear",shape:"round","aria-checked":y?"true":"false","aria-label":"show password",role:"switch",type:"button",onPointerDown:w=>{w.preventDefault()},onClick:this.togglePasswordVisibility},(0,i.h)("ion-icon",{key:"9c88de8f4631d9bde222ce2edf6950d639e04773",slot:"icon-only","aria-hidden":"true",icon:y?I:E})))}get el(){return(0,i.i)(this)}static get watchers(){return{type:["onTypeChange"]}}};return l.style={ios:"",md:""},l})()},80333:(P,a,r)=>{r.d(a,{c:()=>c,g:()=>p,h:()=>d,o:()=>_});var i=r(10467);const d=(o,t)=>null!==t.closest(o),c=(o,t)=>"string"==typeof o&&o.length>0?Object.assign({"ion-color":!0,[`ion-color-${o}`]:!0},t):t,p=o=>{const t={};return(o=>void 0!==o?(Array.isArray(o)?o:o.split(" ")).filter(n=>null!=n).map(n=>n.trim()).filter(n=>""!==n):[])(o).forEach(n=>t[n]=!0),t},f=/^[a-z][a-z0-9+\-.]*:/,_=function(){var o=(0,i.A)(function*(t,n,l,e){if(null!=t&&"#"!==t[0]&&!f.test(t)){const s=document.querySelector("ion-router");if(s)return n?.preventDefault(),s.push(t,l,e)}return!1});return function(n,l,e,s){return o.apply(this,arguments)}}()}}]);