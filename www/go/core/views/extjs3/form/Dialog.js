/* global go, Ext */

/**
 * 
 * Typical usage
 * 
 * var dlg = new Dlg();
 * dlg.load(1).show();
 */

go.form.Dialog = Ext.extend(go.Window, {
	autoScroll: true,
	width: dp(500),
	modal: true,
	entityStore: null,
	currentId: null,
	buttonAlign: 'left',
	layout: "fit",
	
	/**
	 * Redirect to the entity detail view after save.
	 */
	redirectOnSave: true,
	
	panels : null,
	
	initComponent: function () {

		this.panels = [];

		this.formPanel = this.createFormPanel();
		
		//In case this.createFormPanel() is overridden it can provide the entityStore too.
		this.entityStore = this.formPanel.entityStore;
		
		
		//Add a hidden submit button so the form will submit on enter
		this.formPanel.add(new Ext.Button({
					hidden: true,
					hideMode: "offsets",
					type: "submit",
					handler: function() {
						this.submit();
					},
					scope: this
				}));
				
		
		this.formPanel.on("save", function(fp, entity) {
			this.fireEvent("save", this, entity);
		}, this);
		
		this.items = [this.formPanel];

		Ext.applyIf(this,{
			buttons:[
				'->', 
				{
					text: t("Save"),
					handler: function() {this.submit();},
					scope: this
				}
			]
		});

		go.form.Dialog.superclass.initComponent.call(this);		
		
		if(this.entityStore.entity.linkable) {
			this.addCreateLinkButton();
		}

		//deprecated
		if (this.formValues) {
			this.formPanel.setValues(this.formValues);
			delete this.formValues;
		}
		
		this.addEvents({load: true, submit: true});
	},
	
	createFormPanel : function() {
		
		var items = this.initFormItems() || [];
		var layout = 'form';
		
		this.addCustomFields(items);
		
		var count = this.panels.length;
		
		//if items is defined then a panel will be inserted in createTabPanel()
		if(items.length) {
			count++;
		}
		
		if(count > 1) {
			var layout = 'fit';
			items = [this.createTabPanel(items)];
		}
		
		return new go.form.EntityPanel({
			entityStore: this.entityStore,
			items: items,
			layout: layout
		});
	},
	
	addCustomFields : function(items) {
		if(go.Entities.get(this.entityStore).customFields) {
			var fieldsets = go.customfields.CustomFields.getFormFieldSets(this.entityStore);
			fieldsets.forEach(function(fs) {
				//console.log(fs);
				if(fs.fieldSet.isTab) {
					fs.title = null;
					fs.collapsible = false;
					var pnl = new Ext.Panel({
						autoScroll: true,
						title: fs.fieldSet.name,
						items: [fs]
					});
					this.addPanel(pnl);
				}else
				{
					items.push(fs);
				}
			}, this);
		}
	},
	
	createTabPanel : function(items) {
		
		if(items) {
			this.panels.unshift(new Ext.Panel({
				title: t("General"),
				layout: 'form',
				autoScroll: true,
				items: items
			}));
		}
		
		this.tabPanel = new Ext.TabPanel({
			activeTab: 0,
			enableTabScroll:true,
			items: this.panels
		});
		
		
		return this.tabPanel;
	},
	
	addPanel: function(panel) {
		this.panels.push(panel);
	},	

	addCreateLinkButton : function() {
		
		this.getFooterToolbar().insert(0, this.createLinkButton = new go.links.CreateLinkButton());	
		
		this.on("load", function() {
			this.createLinkButton.setEntity(this.entityStore.entity.name, this.currentId);
		}, this);

		this.on("show", function() {
			if(!this.currentId) {
				this.createLinkButton.reset();
			}
		}, this);

		this.on("submit", function(dlg, success, serverId) {			
			this.createLinkButton.setEntity(this.entityStore.entity.name, serverId);
			this.createLinkButton.save();
		}, this);
	
	},
	
	setValues : function(v) {
		this.formPanel.setValues(v);
		
		return this;
	},
	
	getValues : function() {
		return this.formPanel.getValues();
	},

	load: function (id) {
		
		var me = this;
		
		function innerLoad(){
			me.currentId = id;
			me.actionStart();
			me.formPanel.load(id, function(entityValues) {
				me.onLoad(entityValues);
				me.actionComplete();
			}, this);
		}
		
		// The form needs to be rendered before the data can be set
		if(!this.rendered){
			this.on('afterrender',innerLoad,this,{single:true});
		} else {
			innerLoad.call(this);
		}

		return this;
	},
	
	delete: function () {
		
		Ext.MessageBox.confirm(t("Confirm delete"), t("Are you sure you want to delete this item?"), function (btn) {
			if (btn !== "yes") {
				return;
			}
			
			this.entityStore.set({destroy: [this.currentId]}, function (options, success, response) {
				if (response.destroyed) {
					this.hide();
				}
			}, this);
		}, this);
	},

	actionStart: function () {
		if (this.getBottomToolbar()) {
			this.getBottomToolbar().setDisabled(true);
		}
		if (this.getTopToolbar()) {
			this.getTopToolbar().setDisabled(true);
		}

		if (this.getFooterToolbar()) {
			this.getFooterToolbar().setDisabled(true);
		}
	},
	
	onLoad : function(entityValues) {
		this.fireEvent("load", this, entityValues);
//		this.deleteBtn.setDisabled(this.formPanel.entity.permissionLevel < GO.permissionLevels.writeAndDelete);
	},

	onSubmit: function (success, serverId) {
		if (success) {
			this.entityStore.entity.goto(serverId);
		}
	},

	actionComplete: function () {
		if (this.getBottomToolbar()) {
			this.getBottomToolbar().setDisabled(false);
		}

		if (this.getTopToolbar()) {
			this.getTopToolbar().setDisabled(false);
		}
		if (this.getFooterToolbar()) {
			this.getFooterToolbar().setDisabled(false);
		}
	},

	isValid: function () {
		return this.formPanel.isValid();
	},

	focus: function () {		
		this.formPanel.focus();
	},
	
	onBeforeSubmit: function() {
		return true;
	},

	submit: function (cb, scope) {
		
		if(!this.onBeforeSubmit()) {
			return;
		}

		if (!this.isValid()) {
			return;
		}
		
		this.actionStart();

		this.formPanel.submit(function (formPanel, success, serverId) {
			this.actionComplete();
			this.onSubmit(success, serverId);
			this.fireEvent("submit", this, success, serverId);
			
			if(cb) {
				cb.call(scope || this, success, serverId);
			}

			if(!success) {
				return;
			}
			if(this.redirectOnSave) {
				this.entityStore.entity.goto(serverId);
			}
			this.close();
						
		}, this);
	},

	initFormItems: function () {
		return [
//			{
//				xtype: 'textfield',
//				name: 'name',
//				fieldLabel: "Name",
//				anchor: '100%',
//				required: true
//			}
		];
	}
});

Ext.reg("formdialog", go.form.Dialog);
