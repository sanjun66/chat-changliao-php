(window["webpackJsonp"]=window["webpackJsonp"]||[]).push([["chunk-2d0e1d65"],{"7bae":function(e,t,a){"use strict";a.r(t);var l=function(){var e=this,t=e.$createElement,a=e._self._c||t;return a("div",{staticClass:"app-container"},[a("el-form",{staticClass:"demo-form-inline",attrs:{inline:!0,model:e.search}},[a("el-form-item",{attrs:{label:"用户ID"}},[a("el-input",{attrs:{clearable:""},model:{value:e.search.id,callback:function(t){e.$set(e.search,"id",t)},expression:"search.id"}})],1),a("el-form-item",{attrs:{label:"昵称"}},[a("el-input",{attrs:{clearable:""},model:{value:e.search.nick_name,callback:function(t){e.$set(e.search,"nick_name",t)},expression:"search.nick_name"}})],1),a("el-form-item",{attrs:{label:"手机号"}},[a("el-input",{attrs:{clearable:""},model:{value:e.search.phone,callback:function(t){e.$set(e.search,"phone",t)},expression:"search.phone"}})],1),a("el-form-item",{attrs:{label:"邮箱"}},[a("el-input",{attrs:{clearable:""},model:{value:e.search.email,callback:function(t){e.$set(e.search,"email",t)},expression:"search.email"}})],1),a("el-form-item",{attrs:{label:"状态"}},[a("el-select",{attrs:{clearable:""},model:{value:e.search.state,callback:function(t){e.$set(e.search,"state",t)},expression:"search.state"}},[a("el-option",{attrs:{label:"离线",value:"0"}}),a("el-option",{attrs:{label:"在线",value:"1"}})],1)],1),a("el-form-item",{attrs:{label:"注册日期"}},[a("el-date-picker",{attrs:{type:"daterange",format:"yyyy-MM-dd","value-format":"yyyy-MM-dd","range-separator":"至","start-placeholder":"开始日期","end-placeholder":"结束日期"},model:{value:e.date_val,callback:function(t){e.date_val=t},expression:"date_val"}})],1),a("el-form-item",[a("el-button",{attrs:{type:"primary"},on:{click:e.onSubmit}},[e._v("查询")])],1)],1),a("div",{staticStyle:{"text-align":"right","margin-bottom":"15px"}},[a("el-button",{attrs:{type:"success"},on:{click:e.onAddOrUpdate}},[e._v("新增")])],1),a("el-table",{directives:[{name:"loading",rawName:"v-loading",value:e.loading,expression:"loading"}],staticStyle:{width:"100%"},attrs:{data:e.list,border:""}},[a("el-table-column",{attrs:{prop:"id",label:"ID",width:"70"}}),a("el-table-column",{attrs:{prop:"account",label:"账号",width:"150"}}),a("el-table-column",{attrs:{prop:"nick_name",label:"昵称",width:"120"}}),a("el-table-column",{attrs:{prop:"avatar",label:"头像",width:"120"},scopedSlots:e._u([{key:"default",fn:function(e){return[a("el-avatar",{attrs:{size:"large",src:e.row.avatar}})]}}])}),a("el-table-column",{attrs:{prop:"phone",label:"手机号",width:"150"}}),a("el-table-column",{attrs:{prop:"email",label:"邮箱",width:"150"}}),a("el-table-column",{attrs:{prop:"age",label:"年龄",width:"80"}}),a("el-table-column",{attrs:{prop:"sex",label:"性别",width:"100"},scopedSlots:e._u([{key:"default",fn:function(t){return[1==t.row.sex?a("el-tag",{attrs:{type:"success"}},[e._v("男")]):2==t.row.sex?a("el-tag",{attrs:{type:"danger"}},[e._v("女")]):a("el-tag",{attrs:{type:"info"}},[e._v("未知")])]}}])}),a("el-table-column",{attrs:{prop:"state",label:"在线状态",width:"100"},scopedSlots:e._u([{key:"default",fn:function(t){return[1==t.row.state?a("el-tag",{attrs:{type:"success"}},[e._v("在线")]):a("el-tag",{attrs:{type:"danger"}},[e._v("离线")])]}}])}),a("el-table-column",{attrs:{prop:"created_at",label:"注册时间",width:"160"}}),a("el-table-column",{attrs:{fixed:"right",label:"操作",width:"180"},scopedSlots:e._u([{key:"default",fn:function(t){return[a("el-button",{attrs:{type:"primary",size:"mini"},on:{click:function(a){return e.onAddOrUpdate(t.$index,t.row)}}},[e._v("编辑")]),a("el-button",{attrs:{size:"mini"},on:{click:function(a){return e.toFriendList(t.row.id)}}},[e._v("好友列表 ")]),a("el-popconfirm",{attrs:{title:"确定删除吗？"},on:{onConfirm:function(a){return e.handleDelete(t.$index,t.row)}}},[a("el-button",{staticStyle:{"margin-top":"6px"},attrs:{slot:"reference",size:"mini",type:"danger"},slot:"reference"},[e._v("删除 ")])],1)]}}])})],1),a("div",{staticStyle:{margin:"20px 0","text-align":"center"}},[a("el-pagination",{attrs:{background:"",layout:"prev, pager, next","current-page":e.search.page,"page-size":e.pages.limit,total:e.pages.total},on:{"current-change":e.handleCurrentChange,"update:currentPage":function(t){return e.$set(e.search,"page",t)},"update:current-page":function(t){return e.$set(e.search,"page",t)}}})],1),a("el-dialog",{attrs:{title:e.form.id>0?"编辑用户":"添加用户",visible:e.dialogFormVisible,top:"2vh"},on:{"update:visible":function(t){e.dialogFormVisible=t}}},[a("el-form",{ref:"form",attrs:{model:e.form,rules:e.rules}},[a("el-form-item",{attrs:{label:"邮箱",prop:"email","label-width":e.formLabelWidth}},[a("el-input",{model:{value:e.form.email,callback:function(t){e.$set(e.form,"email",t)},expression:"form.email"}})],1),a("el-form-item",{attrs:{label:"手机号",prop:"phone","label-width":e.formLabelWidth}},[a("el-input",{model:{value:e.form.phone,callback:function(t){e.$set(e.form,"phone",t)},expression:"form.phone"}})],1),a("el-form-item",{attrs:{label:"昵称","label-width":e.formLabelWidth}},[a("el-input",{model:{value:e.form.nick_name,callback:function(t){e.$set(e.form,"nick_name",t)},expression:"form.nick_name"}})],1),a("el-form-item",{attrs:{label:"登录密码","label-width":e.formLabelWidth}},[a("el-input",{model:{value:e.form.password,callback:function(t){e.$set(e.form,"password",t)},expression:"form.password"}}),e.form.id>0?a("div",[e._v("为空默认不修改密码")]):a("div",[e._v("为空设置默认密码：123456")])],1),a("el-form-item",{attrs:{label:"年龄","label-width":e.formLabelWidth}},[a("el-input",{attrs:{type:"number"},model:{value:e.form.age,callback:function(t){e.$set(e.form,"age",t)},expression:"form.age"}})],1),a("el-form-item",{attrs:{label:"性别","label-width":e.formLabelWidth}},[a("el-select",{attrs:{placeholder:"请选择性别"},model:{value:e.form.sex,callback:function(t){e.$set(e.form,"sex",t)},expression:"form.sex"}},[a("el-option",{attrs:{label:"未知",value:"0"}}),a("el-option",{attrs:{label:"男",value:"1"}}),a("el-option",{attrs:{label:"女",value:"2"}})],1)],1),a("el-form-item",{attrs:{label:"是否开启好友验证","label-width":e.formLabelWidth}},[a("el-switch",{attrs:{"active-value":1,"inactive-value":0},model:{value:e.form.apply_auth,callback:function(t){e.$set(e.form,"apply_auth",t)},expression:"form.apply_auth"}})],1),a("el-form-item",{attrs:{label:"地址","label-width":e.formLabelWidth}},[a("el-input",{model:{value:e.form.address,callback:function(t){e.$set(e.form,"address",t)},expression:"form.address"}})],1)],1),a("div",{staticClass:"dialog-footer",attrs:{slot:"footer"},slot:"footer"},[a("el-button",{on:{click:function(t){return e.closeVisible()}}},[e._v("取 消")]),a("el-button",{attrs:{type:"primary",loading:e.loadingBut},on:{click:function(t){return e.submitForm()}}},[e._v("确 定")])],1)],1),a("el-dialog",{attrs:{title:"好友列表",width:"80%",visible:e.friendListVisible},on:{"update:visible":function(t){e.friendListVisible=t}}},[a("el-table",{directives:[{name:"loading",rawName:"v-loading",value:e.friendLoading,expression:"friendLoading"}],attrs:{data:e.friendList,"row-key":"friend_id","tree-props":{children:"children",hasChildren:"hasChildren"}}},[e._v("> "),a("el-table-column",{attrs:{property:"remark",label:"群组/好友",width:"200"}}),a("el-table-column",{attrs:{property:"friend_id",label:"用户ID",width:"80"}}),a("el-table-column",{attrs:{property:"account",label:"账号",width:"200"}}),a("el-table-column",{attrs:{property:"avatar",label:"头像",width:"130"},scopedSlots:e._u([{key:"default",fn:function(t){return["-"==t.row.avatar?a("span",[e._v("-")]):a("el-avatar",{attrs:{size:"large",src:t.row.avatar}})]}}])}),a("el-table-column",{attrs:{property:"is_black",width:"100",label:"是否黑名单"},scopedSlots:e._u([{key:"default",fn:function(t){return[0==t.row.is_black?a("el-tag",{attrs:{type:"success"}},[e._v("否")]):1==t.row.is_black?a("el-tag",{attrs:{type:"danger"}},[e._v("是")]):a("span",[e._v("-")])]}}])}),a("el-table-column",{attrs:{prop:"state",label:"在线状态",width:"100"},scopedSlots:e._u([{key:"default",fn:function(t){return[1==t.row.state?a("el-tag",{attrs:{type:"success"}},[e._v("在线")]):0==t.row.state?a("el-tag",{attrs:{type:"danger"}},[e._v("离线")]):a("span",[e._v("-")])]}}])}),a("el-table-column",{attrs:{prop:"sign",label:"个性签名",width:"200"}}),a("el-table-column",{attrs:{fixed:"right",label:"操作",width:"200"},scopedSlots:e._u([{key:"default",fn:function(t){return["-"!=t.row.friend_id?a("div",[a("el-popconfirm",{attrs:{title:"确定删除吗？"},on:{onConfirm:function(a){return e.deleteFriend(t.$index,t.row)}}},[a("el-button",{staticStyle:{"margin-right":"6px"},attrs:{slot:"reference",size:"mini",type:"danger"},slot:"reference"},[e._v(" 删除好友 ")])],1),a("el-button",{attrs:{size:"mini"},on:{click:function(a){return e.showMessage(t.row)}}},[e._v("聊天记录")])],1):e._e()]}}])})],1)],1),a("el-dialog",{attrs:{title:"聊天记录",width:"90%",visible:e.messageVisible},on:{close:e.closeMessageVisible,"update:visible":function(t){e.messageVisible=t}}},[a("el-form",{staticClass:"demo-form-inline",attrs:{inline:!0,model:e.msgPages}},[a("el-form-item",{attrs:{label:"关键字"}},[a("el-input",{attrs:{clearable:""},model:{value:e.msgPages.message,callback:function(t){e.$set(e.msgPages,"message",t)},expression:"msgPages.message"}})],1),a("el-form-item",{attrs:{label:"时间范围"}},[a("el-date-picker",{attrs:{type:"daterange",align:"right","unlink-panels":"","range-separator":"至","start-placeholder":"开始日期","end-placeholder":"结束日期","value-format":"yyyy-MM-dd","picker-options":e.pickerOptions},model:{value:e.dateValue,callback:function(t){e.dateValue=t},expression:"dateValue"}})],1),a("el-form-item",[a("el-button",{attrs:{type:"primary"},on:{click:e.queryMessage}},[e._v("查询")])],1)],1),a("el-table",{directives:[{name:"loading",rawName:"v-loading",value:e.messageLoading,expression:"messageLoading"}],attrs:{data:e.messageList,border:""}},[a("el-table-column",{attrs:{prop:"id",label:"ID",width:"70"}}),a("el-table-column",{attrs:{prop:"from_uid",label:"发送ID",width:"80"},scopedSlots:e._u([{key:"default",fn:function(t){return[e._v(" "+e._s(t.row.from_uid>0?t.row.from_uid:"系统消息")+" ")]}}])}),a("el-table-column",{attrs:{prop:"to_uid",label:"接收ID",width:"80"}}),a("el-table-column",{attrs:{prop:"message_type",label:"消息类型",width:"120"},scopedSlots:e._u([{key:"default",fn:function(t){return[e._v(" "+e._s(e.msgType[t.row.message_type])+" ")]}}])}),a("el-table-column",{attrs:{prop:"message",label:"消息内容"}}),a("el-table-column",{attrs:{prop:"is_revoke",label:"是否撤回",width:"120"},scopedSlots:e._u([{key:"default",fn:function(t){return[0==t.row.is_revoke?a("el-tag",{attrs:{type:"success"}},[e._v("否")]):1==t.row.is_revoke?a("el-tag",{attrs:{type:"danger"}},[e._v("是")]):e._e()]}}])}),a("el-table-column",{attrs:{prop:"quote_id",label:"引用消息ID",width:"120"}}),a("el-table-column",{attrs:{prop:"is_delete",label:"是否删除",width:"120"},scopedSlots:e._u([{key:"default",fn:function(t){return[0==t.row.is_delete?a("el-tag",{attrs:{type:"success"}},[e._v("否")]):1==t.row.is_delete?a("el-tag",{attrs:{type:"danger"}},[e._v("是")]):e._e()]}}])}),a("el-table-column",{attrs:{prop:"created_at",label:"发送时间",width:"160"}})],1),a("div",{staticStyle:{margin:"20px 0","text-align":"center"}},[a("el-pagination",{attrs:{background:"",layout:"prev, pager, next","current-page":e.msgPages.page,"page-size":e.msgPages.limit,total:e.msgPages.total},on:{"current-change":e.msgeCurrentChange,"update:currentPage":function(t){return e.$set(e.msgPages,"page",t)},"update:current-page":function(t){return e.$set(e.msgPages,"page",t)}}})],1)],1)],1)},i=[],s=(a("ac1f"),a("841c"),a("a15b"),a("c24f")),r={name:"UserList",data:function(){return{list:null,loading:!1,pages:{total:0,limit:20},search:{id:"",nick_name:"",state:"",phone:"",email:"",date:"",page:1},date_val:"",loadingBut:!1,formLabelWidth:"160px",dialogFormVisible:!1,form:{id:"",email:"",phone:"",password:"",address:"",age:"",sex:"0",avatar:"",nick_name:"",apply_auth:""},uid:"",friendList:[],friendListVisible:!1,friendLoading:!1,rules:{},messageVisible:!1,messageLoading:!1,messageList:[],friendData:null,msgPages:{page:1,total:0,limit:20,message:"",date:""},msgType:{1:"文本消息",2:" 文件消息",3:" 转发消息",4:"代码消息",5:" 投票消息",6:" 群组公告",7:"好友申请",8:" 登录通知消息",9:"入群退群"},dateValue:null,pickerOptions:{shortcuts:[{text:"最近一周",onClick:function(e){var t=new Date,a=new Date;a.setTime(a.getTime()-6048e5),e.$emit("pick",[a,t])}},{text:"最近一个月",onClick:function(e){var t=new Date,a=new Date;a.setTime(a.getTime()-2592e6),e.$emit("pick",[a,t])}},{text:"最近三个月",onClick:function(e){var t=new Date,a=new Date;a.setTime(a.getTime()-7776e6),e.$emit("pick",[a,t])}}]}}},created:function(){this.getUserList()},methods:{toFriendList:function(e){var t=this;this.uid=e,this.friendListVisible=!0,this.friendLoading=!0,this.friendList=[],Object(s["e"])({uid:e}).then((function(e){if(200==e.data.code){var a=e.data.data.friend_list;for(var l in a){var i={};i.remark=a[l].group_name,i.friend_id="-",i.account="-",i.avatar="-",i.is_black="-",i.state="-",i.sign="-",i.children=a[l].group_list,t.friendList.push(i)}}t.friendLoading=!1}))},deleteFriend:function(e,t){var a=this;Object(s["b"])({uid:this.uid,friend_id:t.friend_id}).then((function(e){a.toFriendList(a.uid)}))},showMessage:function(e){var t=this;this.messageVisible=!0,this.messageLoading=!0,this.friendData=e,Object(s["k"])({uid:this.uid,friend_id:this.friendData.friend_id,page:this.msgPages.page,message:this.msgPages.message,date:this.msgPages.date}).then((function(e){var a=e.data;t.messageLoading=!1,t.messageList=a.data.data,t.msgPages.total=a.data.total,t.msgPages.limit=a.data.per_page,t.msgPages.page=a.data.current_page}))},msgeCurrentChange:function(e){this.msgPages.page=e,this.showMessage(this.friendData)},onSubmit:function(){this.search.page=1,this.getUserList()},queryMessage:function(){this.dateValue?this.msgPages.date=this.dateValue.join(","):this.msgPages.date="",this.msgPages.page=1,this.showMessage(this.friendData)},closeMessageVisible:function(){this.msgPages={page:1,total:0,limit:20,message:"",date:""},this.dateValue=null},getUserList:function(){var e=this;if(this.loading=!0,this.date_val){var t="";for(var a in this.date_val)t+=this.date_val[a],0==a&&(t+=",");this.search.date=t}else this.search.date="";Object(s["m"])(this.search).then((function(t){var a=t.data;e.loading=!1,e.list=a.data.data,e.pages.total=a.data.total,e.pages.limit=a.data.per_page,e.search.page=a.data.current_page}))},handleDelete:function(e,t){var a=this;Object(s["d"])(t.id).then((function(e){a.getUserList()}))},submitForm:function(){this.upOrAddUser()},upOrAddUser:function(){var e=this;this.loadingBut=!0,Object(s["w"])(this.form).then((function(t){200==t.data.code&&(e.dialogFormVisible=!1,e.getUserList()),e.loadingBut=!1}))},handleCurrentChange:function(e){this.search.page=e,this.getUserList()},onAddOrUpdate:function(e,t){this.form=null,this.dialogFormVisible=!0,this.form={id:"",email:"",phone:"",password:"",address:"",age:"",sex:"0",avatar:"",nick_name:"",apply_auth:""},t&&(this.form=Object.assign({},t))},closeVisible:function(){this.dialogFormVisible=!1}}},n=r,o=a("2877"),d=Object(o["a"])(n,l,i,!1,null,"19206e1a",null);t["default"]=d.exports}}]);