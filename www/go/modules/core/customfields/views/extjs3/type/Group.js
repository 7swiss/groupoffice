Ext.ns("go.modules.core.customfields.type");

go.modules.core.customfields.type.Group = Ext.extend(go.modules.core.customfields.type.Text, {
	
	name : "Group",
	
	label: t("Group"),
	
	iconCls: "ic-group",	
	
	/**
	 * Return dialog to edit this type of field
	 * 
	 * @returns {go.modules.core.customfields.FieldDialog}
	 */
	getDialog : function() {
		return new go.modules.core.customfields.type.GroupDialog();
	},
	
	/**
	 * Render's the custom field value for the detail views
	 * 
	 * @param {mixed} value
	 * @param {object} data Complete entity
	 * @param {object} customfield Field entity from custom fields
	 * @param {go.detail.Property} cmp The property component that renders the value
	 * @returns {unresolved}
	 */
	renderDetailView: function (value, data, customfield, cmp) {		
		
		if(!value) {
			return "";
		}
		
		go.Stores.get("Group").get([value], function(groups) {
			if(!groups[0]) {
				console.warn("Group not found for ID: " + value);
				return;
			}
			cmp.setValue(groups[0].name);
			cmp.setVisible(true);
		});
		
	},
	
	/**
	 * Returns config oject to create the form field 
	 * 
	 * @param {object} customfield customfield Field entity from custom fields
	 * @param {object} config Extra config options to apply to the form field
	 * @returns {Object}
	 */
	createFormFieldConfig: function (customfield, config) {
		var c = go.modules.core.customfields.type.Select.superclass.createFormFieldConfig.call(this, customfield, config);
		c.xtype = "groupcombo";
		c.hiddenName = c.name;
		delete c.name;
		
		return c;
	},

	getFieldType: function () {
		return go.data.types.Group;
	},
	
	/**
	 * Get the field definition for creating Ext.data.Store's
	 * 
	 * Also the customFieldType (this) and customField (Entity Field) are added
	 * 
	 * @see https://docs.sencha.com/extjs/3.4.0/#!/api/Ext.data.Field
	 * @returns {Object}
	 */
	getFieldDefinition : function(field) {		
		var c = go.modules.core.customfields.type.Select.superclass.getFieldDefinition.call(this, field);
		c.key = field.databaseName;		
		return c;
	}
	
	
});

go.modules.core.customfields.CustomFields.registerType(new go.modules.core.customfields.type.Group());
