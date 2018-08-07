go.modules.core.core.ShareWindow = Ext.extend(go.form.Dialog, {
	title: t('Share'),
	entityStore: go.Stores.get("Acl"),
	height: dp(600),
	width: dp(800),
	
	initComponent : function() {
		this.buttons = [
			'->', this.shareBtn = new Ext.Button({
				cls: "raised",
				text: t("Share"),
				handler: this.submit,
				scope: this
			})];
		
		go.modules.core.core.ShareWindow.superclass.initComponent.call(this);
	},
	initFormItems: function () {
		return [
			this.sharePanel = new go.modules.core.core.SharePanel({
				anchor: '100% -' + dp(32),
				hideLabel: true			
			})
		];
	},
	onSubmit : function() {
		//don't route to page
	}
});