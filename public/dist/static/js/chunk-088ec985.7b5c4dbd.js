(window["webpackJsonp"]=window["webpackJsonp"]||[]).push([["chunk-088ec985"],{8593:function(e,t,r){"use strict";r.d(t,"h",(function(){return a})),r.d(t,"e",(function(){return o})),r.d(t,"o",(function(){return i})),r.d(t,"a",(function(){return l})),r.d(t,"g",(function(){return u})),r.d(t,"k",(function(){return d})),r.d(t,"r",(function(){return s})),r.d(t,"c",(function(){return c})),r.d(t,"j",(function(){return m})),r.d(t,"f",(function(){return f})),r.d(t,"p",(function(){return p})),r.d(t,"b",(function(){return g})),r.d(t,"l",(function(){return b})),r.d(t,"s",(function(){return h})),r.d(t,"i",(function(){return v})),r.d(t,"q",(function(){return _})),r.d(t,"m",(function(){return O})),r.d(t,"n",(function(){return j})),r.d(t,"d",(function(){return x}));var n=r("b775");function a(e){return Object(n["a"])({url:"/admin/logs/",method:"get",params:e})}function o(e){return Object(n["a"])({url:"/admin",method:"get",params:e})}function i(e){return Object(n["a"])({url:"/admin",method:"put",data:e})}function l(e){return Object(n["a"])({url:"/admin",method:"delete",data:e})}function u(e){return Object(n["a"])({url:"/admin/index",method:"get",params:e})}function d(e){return Object(n["a"])({url:"/admin/roles",method:"get",params:e})}function s(e){return Object(n["a"])({url:"/admin/roles",method:"put",data:e})}function c(e){return Object(n["a"])({url:"/admin/roles",method:"delete",data:e})}function m(e){return Object(n["a"])({url:"/admin/roles/auth",method:"get",params:e})}function f(e){return Object(n["a"])({url:"/admin/authorities",method:"get"})}function p(e){return Object(n["a"])({url:"/admin/authorities",method:"put",data:e})}function g(e){return Object(n["a"])({url:"/admin/authorities",method:"delete",data:e})}function b(e){return Object(n["a"])({url:"/admin/smsInfo",method:"get",data:e})}function h(e){return Object(n["a"])({url:"/admin/smsSave",method:"PUT",data:e})}function v(e){return Object(n["a"])({url:"/admin/ossInfo",method:"get",data:e})}function _(e){return Object(n["a"])({url:"/admin/ossSave",method:"PUT",data:e})}function O(e){return Object(n["a"])({url:"/admin/Version",method:"get",data:e})}function j(e){return Object(n["a"])({url:"/admin/Version",method:"PUT",data:e})}function x(e){return Object(n["a"])({url:"/admin/version/"+e,method:"delete"})}},db74:function(e,t,r){"use strict";r.r(t);var n=function(){var e=this,t=e.$createElement,r=e._self._c||t;return r("div",{staticClass:"app-container"},[r("div",{staticStyle:{"text-align":"right","margin-bottom":"15px"}},[r("el-button",{attrs:{type:"success"},on:{click:function(t){return e.onAddOrUpdate()}}},[e._v("新增")])],1),r("el-table",{directives:[{name:"loading",rawName:"v-loading",value:e.loading,expression:"loading"}],staticStyle:{width:"100%"},attrs:{data:e.list,border:""}},[r("el-table-column",{attrs:{prop:"id",label:"ID",width:"80"}}),r("el-table-column",{attrs:{prop:"platform",label:"平台"}}),r("el-table-column",{attrs:{prop:"version_code",label:"版本号"}}),r("el-table-column",{attrs:{prop:"version_name",label:"版本名称"}}),r("el-table-column",{attrs:{prop:"forced_update",label:"强制更新"},scopedSlots:e._u([{key:"default",fn:function(t){return[1==t.row.forced_update?r("el-tag",{attrs:{type:"success"}},[e._v("强制")]):r("el-tag",{attrs:{type:"danger"}},[e._v("不强制")])]}}])}),r("el-table-column",{attrs:{prop:"update_url",label:"更新链接"}}),r("el-table-column",{attrs:{prop:"desc",label:"更新说明"}}),r("el-table-column",{attrs:{label:"操作",width:"150"},scopedSlots:e._u([{key:"default",fn:function(t){return[r("el-popconfirm",{attrs:{title:"确定删除吗？"},on:{onConfirm:function(r){return e.handleDelete(t.$index,t.row)}}},[r("el-button",{staticStyle:{"margin-left":"6px"},attrs:{slot:"reference",size:"mini",type:"danger"},slot:"reference"},[e._v("删除 ")])],1)]}}])})],1),r("div",{staticStyle:{margin:"20px 0","text-align":"center"}},[r("el-pagination",{attrs:{background:"",layout:"prev, pager, next","current-page":e.search.page,"page-size":e.pages.limit,total:e.pages.total},on:{"current-change":e.handleCurrentChange,"update:currentPage":function(t){return e.$set(e.search,"page",t)},"update:current-page":function(t){return e.$set(e.search,"page",t)}}})],1),r("el-dialog",{staticClass:"el-form-dialog",attrs:{title:e.form.id>0?"版本-编辑":"版本-添加",visible:e.dialogFormVisible,top:"2vh","close-on-click-modal":!1,"before-close":e.handleClose},on:{"update:visible":function(t){e.dialogFormVisible=t}}},[r("el-form",{ref:"form",attrs:{model:e.form,rules:e.rules,"label-width":"100px"}},[r("el-form-item",{attrs:{label:"平台",prop:"platform"}},[r("el-radio-group",{model:{value:e.form.platform,callback:function(t){e.$set(e.form,"platform",t)},expression:"form.platform"}},[r("el-radio",{attrs:{label:"iOS"}},[e._v("iOS")]),r("el-radio",{attrs:{label:"Android"}},[e._v("Android")])],1)],1),r("el-form-item",{attrs:{label:"版本号",prop:"version_code"}},[r("el-input",{model:{value:e.form.version_code,callback:function(t){e.$set(e.form,"version_code",t)},expression:"form.version_code"}})],1),r("el-form-item",{attrs:{label:"版本名称",prop:"version_name"}},[r("el-input",{model:{value:e.form.version_name,callback:function(t){e.$set(e.form,"version_name",t)},expression:"form.version_name"}})],1),r("el-form-item",{attrs:{label:"更新链接",prop:"update_url"}},[r("el-input",{model:{value:e.form.update_url,callback:function(t){e.$set(e.form,"update_url",t)},expression:"form.update_url"}})],1),r("el-form-item",{attrs:{label:"更新说明",prop:"desc"}},[r("el-input",{attrs:{type:"textarea"},model:{value:e.form.desc,callback:function(t){e.$set(e.form,"desc",t)},expression:"form.desc"}})],1),r("el-form-item",{attrs:{label:"是否强制更新",prop:"forced_update"}},[r("el-radio-group",{model:{value:e.form.forced_update,callback:function(t){e.$set(e.form,"forced_update",t)},expression:"form.forced_update"}},[r("el-radio",{attrs:{label:"1"}},[e._v("是")]),r("el-radio",{attrs:{label:"0"}},[e._v("否")])],1)],1)],1),r("div",{staticClass:"dialog-footer",attrs:{slot:"footer"},slot:"footer"},[r("el-button",{on:{click:function(t){return e.handleClose()}}},[e._v("取 消")]),r("el-button",{attrs:{type:"primary",loading:e.loadingBut},on:{click:function(t){return e.submitForm()}}},[e._v("确 定")])],1)],1)],1)},a=[],o=(r("ac1f"),r("841c"),r("8593")),i={name:"app",data:function(){return{that:this,list:null,loading:!1,loadingBut:!1,dialogFormVisible:!1,pages:{total:0,limit:20},search:{admin_name:"",phone:"",email:"",state:"-1",page:1,login_date:""},form:{id:"",platform:"",version_code:"",version_name:"",forced_update:"0",update_url:"",desc:""},rules:{platform:[{required:!0,message:"请选择平台",trigger:"blur"}],version_code:[{required:!0,message:"请输入版本号",trigger:"blur"}],version_name:[{required:!0,message:"请输入版本名称",trigger:"blur"}],update_url:[{required:!0,message:"请输入更新链接",trigger:"blur"}]}}},created:function(){this.getVersionList()},methods:{getVersionList:function(){var e=this;this.loading=!0,Object(o["m"])(this.search).then((function(t){var r=t.data;console.log(r),e.loading=!1,200==r.code&&(e.loading=!1,e.list=r.data.data,e.pages.total=r.data.total,e.pages.limit=r.data.per_page,e.search.page=r.data.current_page)}))},handleCurrentChange:function(e){this.search.page=e,this.getVersionList()},submitForm:function(){var e=this;this.$refs["form"].validate((function(t){t&&(e.loadingBut=!0,Object(o["n"])(e.form).then((function(t){e.loadingBut=!1,e.dialogFormVisible=!1,e.search.page=1,e.getVersionList()})).catch((function(){e.loadingBut=!1})))}))},handleClose:function(){this.dialogFormVisible=!1},onAddOrUpdate:function(e){this.form=null,this.dialogFormVisible=!0,this.form={id:"",platform:"",version_code:"",version_name:"",forced_update:"0",update_url:"",desc:""},e&&(this.form=Object.assign({},e))},handleDelete:function(e,t){var r=this;Object(o["d"])(t.id).then((function(e){r.search.page=1,r.getVersionList()}))}}},l=i,u=r("2877"),d=Object(u["a"])(l,n,a,!1,null,"6e4072e9",null);t["default"]=d.exports}}]);