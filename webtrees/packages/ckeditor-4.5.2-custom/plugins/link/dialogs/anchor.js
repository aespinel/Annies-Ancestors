CKEDITOR.dialog.add("anchor",function(b){function a(d,c){return d.createFakeElement(d.document.createElement("a",{attributes:c}),"cke_anchor","anchor")}return{title:b.lang.link.anchor.title,minWidth:300,minHeight:60,onOk:function(){var d=CKEDITOR.tools.trim(this.getValueOf("info","txtName")),d={id:d,name:d,"data-cke-saved-name":d};if(this._.selectedElement){this._.selectedElement.data("cke-realelement")?(d=a(b,d),d.replace(this._.selectedElement),CKEDITOR.env.ie&&b.getSelection().selectElement(d)):this._.selectedElement.setAttributes(d)}else{var c=b.getSelection(),c=c&&c.getRanges()[0];c.collapsed?(d=a(b,d),c.insertNode(d)):(CKEDITOR.env.ie&&9>CKEDITOR.env.version&&(d["class"]="cke_anchor"),d=new CKEDITOR.style({element:"a",attributes:d}),d.type=CKEDITOR.STYLE_INLINE,b.applyStyle(d))}},onHide:function(){delete this._.selectedElement},onShow:function(){var f=b.getSelection(),c=f.getSelectedElement(),h=c&&c.data("cke-realelement"),g=h?CKEDITOR.plugins.link.tryRestoreFakeAnchor(b,c):CKEDITOR.plugins.link.getSelectedLink(b);g&&(this._.selectedElement=g,this.setValueOf("info","txtName",g.data("cke-saved-name")||""),!h&&f.selectElement(g),c&&(this._.selectedElement=c));this.getContentElement("info","txtName").focus()},contents:[{id:"info",label:b.lang.link.anchor.title,accessKey:"I",elements:[{type:"text",id:"txtName",label:b.lang.link.anchor.name,required:!0,validate:function(){return this.getValue()?!0:(alert(b.lang.link.anchor.errorName),!1)}}]}]}});