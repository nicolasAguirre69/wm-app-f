import{a as l,c as r}from"./button-DdEySl9s.js";import{j as s,$ as d,r as o}from"./app-O2YUB9PR.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const c=[["path",{d:"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z",key:"1a8usu"}],["path",{d:"m15 5 4 4",key:"1mk7zo"}]],T=l("Pencil",c);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const n=[["path",{d:"M5 12h14",key:"1ays0h"}],["path",{d:"M12 5v14",key:"s699le"}]],g=l("Plus",n);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const m=[["path",{d:"M3 6h18",key:"d0wm0j"}],["path",{d:"M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6",key:"4alrt4"}],["path",{d:"M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2",key:"v07s0e"}],["line",{x1:"10",x2:"10",y1:"11",y2:"17",key:"1uufr5"}],["line",{x1:"14",x2:"14",y1:"11",y2:"17",key:"xtxkd"}]],j=l("Trash2",m);function w({links:a}){return a.length<=3?null:s.jsx("nav",{className:"flex flex-wrap items-center gap-1",children:a.map((e,t)=>e.url===null?s.jsx("span",{className:"text-muted-foreground px-3 py-1.5 text-sm",dangerouslySetInnerHTML:{__html:e.label}},t):s.jsx(d,{href:e.url,preserveScroll:!0,className:r("rounded-md px-3 py-1.5 text-sm transition-colors",e.active?"bg-primary text-primary-foreground":"hover:bg-accent hover:text-accent-foreground"),dangerouslySetInnerHTML:{__html:e.label}},t))})}const i=o.forwardRef(({className:a,...e},t)=>s.jsx("div",{className:"relative w-full overflow-x-auto",children:s.jsx("table",{ref:t,className:r("w-full caption-bottom text-sm",a),...e})}));i.displayName="Table";const p=o.forwardRef(({className:a,...e},t)=>s.jsx("thead",{ref:t,className:r("[&_tr]:border-b",a),...e}));p.displayName="TableHeader";const x=o.forwardRef(({className:a,...e},t)=>s.jsx("tbody",{ref:t,className:r("[&_tr:last-child]:border-0",a),...e}));x.displayName="TableBody";const f=o.forwardRef(({className:a,...e},t)=>s.jsx("tr",{ref:t,className:r("hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors",a),...e}));f.displayName="TableRow";const u=o.forwardRef(({className:a,...e},t)=>s.jsx("th",{ref:t,className:r("text-muted-foreground h-11 px-3 text-left align-middle font-medium [&:has([role=checkbox])]:pr-0",a),...e}));u.displayName="TableHead";const b=o.forwardRef(({className:a,...e},t)=>s.jsx("td",{ref:t,className:r("p-3 align-middle [&:has([role=checkbox])]:pr-0",a),...e}));b.displayName="TableCell";const h=o.forwardRef(({className:a,...e},t)=>s.jsx("caption",{ref:t,className:r("text-muted-foreground mt-4 text-sm",a),...e}));h.displayName="TableCaption";export{g as P,i as T,p as a,f as b,u as c,x as d,b as e,T as f,j as g,w as h};
