(window["webpackJsonp"]=window["webpackJsonp"]||[]).push([["chunk-c3760912"],{"747c":function(t,n,e){"use strict";e.r(n);var r=function(){var t=this,n=t.$createElement,e=t._self._c||n;return e("div",{staticClass:"app-container"},[t.info?e("el-form",{attrs:{"label-width":"140px"}},[e("el-form-item",{attrs:{label:"存储"}},[e("el-radio-group",{model:{value:t.info.oss[0].value,callback:function(n){t.$set(t.info.oss[0],"value",n)},expression:"info.oss[0].value"}},[e("el-radio",{attrs:{label:"1"}},[t._v("本地")]),e("el-radio",{attrs:{label:"2"}},[t._v(" 亚马逊 ")])],1)],1),2==t.info.oss[0].value?e("div",t._l(t.info.aws,(function(n,r){return e("el-form-item",{key:r,attrs:{label:n.edit}},[e("el-input",{model:{value:n.value,callback:function(e){t.$set(n,"value",e)},expression:"item.value"}})],1)})),1):t._e(),e("el-form-item",[e("el-button",{attrs:{type:"primary",loading:t.loadingBut},on:{click:t.onSubmit}},[t._v("保存")])],1)],1):t._e()],1)},a=[],u=e("b85c"),o=e("8593"),i={name:"oss-info",data:function(){return{info:null,loadingBut:!1}},created:function(){this.getOssInfo()},methods:{onSubmit:function(){var t=this,n={};for(var e in this.info){var r,a=Object(u["a"])(this.info[e]);try{for(a.s();!(r=a.n()).done;){var i=r.value;n[i.key]=i.value}}catch(d){a.e(d)}finally{a.f()}}this.loadingBut=!0,Object(o["q"])(n).then((function(n){200==n.data.code&&t.$message({message:"操作成功",type:"success"}),t.getOssInfo(),t.loadingBut=!1}))},getOssInfo:function(){var t=this;Object(o["i"])().then((function(n){console.log(n),(n.data.code=200)&&(t.info=n.data.data)}))}}},d=i,c=e("2877"),l=Object(c["a"])(d,r,a,!1,null,"6352d696",null);n["default"]=l.exports},8593:function(t,n,e){"use strict";e.d(n,"h",(function(){return a})),e.d(n,"e",(function(){return u})),e.d(n,"o",(function(){return o})),e.d(n,"a",(function(){return i})),e.d(n,"g",(function(){return d})),e.d(n,"k",(function(){return c})),e.d(n,"r",(function(){return l})),e.d(n,"c",(function(){return s})),e.d(n,"j",(function(){return f})),e.d(n,"f",(function(){return m})),e.d(n,"p",(function(){return b})),e.d(n,"b",(function(){return h})),e.d(n,"l",(function(){return v})),e.d(n,"s",(function(){return O})),e.d(n,"i",(function(){return p})),e.d(n,"q",(function(){return j})),e.d(n,"m",(function(){return g})),e.d(n,"n",(function(){return k})),e.d(n,"d",(function(){return w}));var r=e("b775");function a(t){return Object(r["a"])({url:"/admin/logs/",method:"get",params:t})}function u(t){return Object(r["a"])({url:"/admin",method:"get",params:t})}function o(t){return Object(r["a"])({url:"/admin",method:"put",data:t})}function i(t){return Object(r["a"])({url:"/admin",method:"delete",data:t})}function d(t){return Object(r["a"])({url:"/admin/index",method:"get",params:t})}function c(t){return Object(r["a"])({url:"/admin/roles",method:"get",params:t})}function l(t){return Object(r["a"])({url:"/admin/roles",method:"put",data:t})}function s(t){return Object(r["a"])({url:"/admin/roles",method:"delete",data:t})}function f(t){return Object(r["a"])({url:"/admin/roles/auth",method:"get",params:t})}function m(t){return Object(r["a"])({url:"/admin/authorities",method:"get"})}function b(t){return Object(r["a"])({url:"/admin/authorities",method:"put",data:t})}function h(t){return Object(r["a"])({url:"/admin/authorities",method:"delete",data:t})}function v(t){return Object(r["a"])({url:"/admin/smsInfo",method:"get",data:t})}function O(t){return Object(r["a"])({url:"/admin/smsSave",method:"PUT",data:t})}function p(t){return Object(r["a"])({url:"/admin/ossInfo",method:"get",data:t})}function j(t){return Object(r["a"])({url:"/admin/ossSave",method:"PUT",data:t})}function g(t){return Object(r["a"])({url:"/admin/Version",method:"get",data:t})}function k(t){return Object(r["a"])({url:"/admin/Version",method:"PUT",data:t})}function w(t){return Object(r["a"])({url:"/admin/version/"+t,method:"delete"})}}}]);