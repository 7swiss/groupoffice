Ext.ns("go.modules.community.addressbook.customfield");

go.modules.community.addressbook.customfield.Contact = Ext.extend(go.modules.core.customfields.type.Text, {
	
	name : "Contact",
	
	label: t("Contact"),
	
	iconCls: "ic-person",	
	
	/**
	 * Return dialog to edit this type of field
	 * 
	 * @returns {go.modules.core.customfields.FieldDialog}
	 */
	getDialog : function() {
		return new  go.modules.community.addressbook.customfield.ContactDialog();
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
		
		go.Stores.get("Contact").get([value], function(contacts) {
			if(!contacts[0]) {
				console.warn("Contact not found for ID: " + value);
				return;
			}
			cmp.setValue(contacts[0].name);
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
		console.log(customfield);
		c.xtype = "contactcombo";
		c.isOrganization = customfield.options.isOrganization; 
		c.hiddenName = c.name;
		delete c.name;
		
		return c;
	},

	getFieldType: function () {
		return go.data.types.Contact;
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

go.modules.core.customfields.CustomFields.registerType(new go.modules.community.addressbook.customfield.Contact());

