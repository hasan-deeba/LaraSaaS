module.exports=[80318,a=>{a.v({className:"space_grotesk_7b9bba7b-module__WMMIgW__className",variable:"space_grotesk_7b9bba7b-module__WMMIgW__variable"})},1446,a=>{a.v({className:"jetbrains_mono_a852f2ac-module__Ttl-Xq__className",variable:"jetbrains_mono_a852f2ac-module__Ttl-Xq__variable"})},18172,a=>{"use strict";var b=a.i(28881),c=a.i(80318);let d={className:c.default.className,style:{fontFamily:"'Space Grotesk', 'Space Grotesk Fallback'",fontStyle:"normal"}};null!=c.default.variable&&(d.variable=c.default.variable);var e=a.i(1446);let f={className:e.default.className,style:{fontFamily:"'JetBrains Mono', 'JetBrains Mono Fallback'",fontStyle:"normal"}};null!=e.default.variable&&(f.variable=e.default.variable);let g=`
(function () {
  try {
    var stored = localStorage.getItem("ls-theme");
    var dark = stored ? stored === "dark" : true;
    document.documentElement.classList.toggle("dark", dark);
    var locale = localStorage.getItem("ls-locale");
    if (locale) {
      document.documentElement.lang = locale;
      document.documentElement.dir = locale === "ar" ? "rtl" : "ltr";
    }
  } catch (e) {
    document.documentElement.classList.add("dark");
  }
})();
`;a.s(["default",0,function({children:a}){return(0,b.jsxs)("html",{lang:"en",className:"dark",suppressHydrationWarning:!0,children:[(0,b.jsx)("head",{children:(0,b.jsx)("script",{dangerouslySetInnerHTML:{__html:g}})}),(0,b.jsx)("body",{className:`${d.variable} ${f.variable} font-display noise`,children:a})]})},"metadata",0,{title:"LaraSaaS — The drop-in SaaS engine for Laravel + React",description:"Stripe billing, dynamic pricing, announcements and feature flags for any Laravel app. Composer-installable. Uses YOUR User model, YOUR auth, YOUR theme.",openGraph:{title:"LaraSaaS — Ship your SaaS, keep your stack",description:"Composer-installable SaaS boilerplate: Cashier billing, config-driven pricing, announcements and Pennant feature flags with React components."}}],18172)},80688,a=>{a.n(a.i(18172))}];

//# sourceMappingURL=%5Broot-of-the-server%5D__0pd8ztq._.js.map