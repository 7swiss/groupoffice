/* global Ext, go */

go.form.EntityPanel = Ext.extend(Ext.form.FormPanel, {
	currentId : null, 
	entityStore: null,
	buttonAlign: 'left',
	autoScroll: true,
	entity: null,
	values : null,
	
	initComponent : function() {
		go.form.EntityPanel.superclass.initComponent.call(this);			
		
		this.values = {};
		
		this.getForm().trackResetOnLoad = true;
		
		this.addEvents({load: true, setvalues: true});
	},	
	
	onChanges : function(entityStore, added, changed, destroyed) {
		//don't update on our own submit
		if(this.submitting) {
			return;
		}
		var entity = added[this.currentId] || changed[this.currentId] || false;
		if(entity) {			
			this.entity = entity;
			//TODO, This will bluntly overwrite user's modification when modified.
			this.getForm().setValues(entity);
		}		
	},
	
	isValid : function() {
		return this.getForm().isValid();
	},
	
	load: function (id, callback, scope) {
		this.currentId = id;

		var me = this;

		this.entityStore.single(id).then(function(entity) {
			me.setValues(entity, true);
			me.entity = entity;
			
			if(callback) {
				callback.call(scope || me, entity);
			}
			
			me.fireEvent("load", me, entity);
		});
	},
	
	getValues : function (dirtyOnly) {	
		var v = {};
		for(var name in this.values) {
			if(!dirtyOnly || this.entity == null || !go.util.isEqual(this.entity[name], this.values[name])) {
				v[name] = this.values[name];
			}
		}
		
		Ext.apply(v, this.getForm().getFieldValues(dirtyOnly));
		return v;
	},
	
	setValues : function(v, trackReset) {
		var field, name;
		
		//set all non form values.
		for(name in v) {		
			field = this.getForm().findField(name);
			if(!field) {
				//Use clone otherwise dirty check will never work because of the reference
				this.values[name] = go.util.clone(v[name]);
			}
		}

		//Set the form values after. It's important to do this after setting this.values otherwise it will add joined object value names like customFields.name
		var oldReset = this.getForm().trackResetOnLoad;
		this.getForm().trackResetOnLoad = trackReset;
		this.getForm().setValues(v);
		this.getForm().trackResetOnLoad = oldReset;
		
		this.fireEvent('setvalues', this, v);
		return this;
	},

	submit: function (cb, scope) {

		if (!this.isValid()) {
			return;
		}		
		//get only modified values on existing items, otherwise get all values.
		var id, params = {}, values = this.getValues(!!this.currentId), me = this;
		
		if (this.currentId) {

			id = this.currentId;

			params.update = {};
			params.update[this.currentId] = values;
		} else {

			id = Ext.id();
			params.create = {};
			params.create[id] = values;
		}

		this.submitting = true;

		this.fireEvent('beforesubmit', this, values);
		
		return me.entityStore.set(params).then(function(response) {

			var saved = (params.create ? response.created : response.updated) || {};
			if (id in saved) {
				me.fireEvent("save", me, values, serverId);

				var serverId = params.create ? response.created[id].id : id;

				if(cb) {
					cb.call(scope, me, true, serverId);
				}

				me.fireEvent("submit", me, true, serverId);
			} else
			{
				//something went wrong
				var notSaved = (params.create ? response.notCreated : response.notUpdated) || {};
				if (!notSaved[id]) {
					notSaved[id] = {type: "unknown"};
				}

				switch (notSaved[id].type) {
					case "forbidden":
						Ext.MessageBox.alert(t("Access denied"), t("Sorry, you don't have permissions to update me item"));
						break;

					default:

						//mark validation errors
						for(var name in notSaved[id].validationErrors) {
							var field = me.getForm().findField(name);
							if(field) {
								field.markInvalid(notSaved[id].validationErrors[name].description);

							} else
							{
								console.warn("Could not find form field for server error " + name,notSaved[id].validationErrors[name]);
							}
							if(!response.message) {
								response.message = notSaved[id].validationErrors[name].description;
							}
						}
						/**
						 * 
						 * You can cancel the error message with me event:
						 * 
						 * initComponent: function() {
						 * 	go.modules.business.wopi.ServiceDialog.superclass.initComponent.call(me);
						 * 
						 * 	me.formPanel.on("beforesubmiterror", function(form, success, id, error) {			
						 * 		if(error.validationErrors.type) {
						 * 			Ext.MessageBox.alert(t("Error"), t("You can only add one service of the same type"));
						 * 			return false; //return false to cancel default error message
						 * 		}
						 * 	}, me);
						 * },
						 */
						if(me.fireEvent("beforesubmiterror", me, false, null, notSaved[id])) {
							Ext.MessageBox.alert(t("Error"), t("Sorry, an unexpected error occurred: ") + (response.message || "unknown error"));
						}
						break;
				}
				if(cb) {
					cb.call(scope, me, false, null);
				}
				me.fireEvent("submit", me, false, null, notSaved[id]);

				return response;
			}
		}, me).catch(function(error){
			if(cb) {
				cb.call(scope, me, false, null);
			}
			me.fireEvent("submit", me, false, null, error);

			return error;
		}).finally(function() {
			me.submitting = false;
		})

	}
});

Ext.reg("entityform", go.form.EntityPanel);