go.modules.community.task.TasklistDialog = Ext.extend(go.form.Dialog, {
	title: t("Tasklists", "tasks"),
	entityStore: "TasksTasklist",
	titleField: "name",
	width: dp(800),
	height: dp(600),
	initFormItems: function () {
		this.addPanel(new go.permissions.SharePanel());

		return [{
				xtype: 'fieldset',
				items: [
					{
						xtype: 'textfield',
						name: 'name',
						fieldLabel: t("Name"),
						anchor: '100%',
						allowBlank: false
					}]
			}
		];
	}
});
