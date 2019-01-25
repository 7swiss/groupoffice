
/* global go, Ext */

/**
 * 
 * 
 * //Inserting records will trigger server update too:
 * var store = this.noteGrid.store;
						var myRecordDef = Ext.data.Record.create(store.fields);

						store.insert(0, new myRecordDef({
							name: "New",
							content: "Testing",
							noteBookId: this.addNoteBookId
						}));
						
						store.commitChanges();
 */
go.data.Store = Ext.extend(Ext.data.JsonStore, {
	
	entityStore: null,
	
	autoDestroy: true,
	
	/**
	 * true when load or loaddata has been called.
	 * 
	 * @var bool
	 */
	loaded : false,
	
	loading : false,
	
	remoteSort : true,
	
	
	constructor: function (config) {
		
		config = config || {};
		config.root = "records";
		
		go.data.Store.superclass.constructor.call(this, Ext.applyIf(config, {
			idProperty:  "id",
			paramNames: {
				start: 'position', // The parameter name which specifies the start row
				limit: 'limit', // The parameter name which specifies number of rows to return
				sort: 'sort', // The parameter name which specifies the column to sort on
				dir: 'dir'       // The parameter name which specifies the sort direction
			},
			proxy: config.entityStore ? new go.data.EntityStoreProxy(config) : new go.data.JmapProxy(config)
		}));
		
		this.setup();
	
	},
	
	loadData : function(o, append){
		var old = this.loading;
		this.loading = true;
			
		if(this.proxy instanceof go.data.EntityStoreProxy) {
			this.proxy.preFetchEntities(o.records, function() {
				go.data.Store.superclass.loadData.call(this, o, append);	
				this.loading = old;		
			}, this);
		} else
		{
			go.data.Store.superclass.loadData.call(this, o, append);	
			this.loading = old;
		}
	},
	
	sort : function(fieldName, dir) {
		//Reload first page data set on sort
		if(this.lastOptions && this.lastOptions.params) {
			this.lastOptions.params.position = 0;
			this.lastOptions.add = false;
		}
		
		return go.data.Store.superclass.sort.call(this, fieldName, dir);
	},
	
	//created this because grouping store must share this.
	setup : function() {
		if(!this.baseParams) {
			this.baseParams = {};
		}
		
		if(!this.baseParams.filter) {
			this.baseParams.filter = {};
		}		
		
		if(this.entityStore) {			
			this.initEntityStore();
		}
	},
	
	
	load : function(params) {
		this.loading = true;
		
		this.on("load", function() {
			this.loading = false;
			this.loaded = true;
		}, this, {single: true});
		
		return go.data.Store.superclass.load.call(this, params);		
		
	},
	
	initEntityStore : function() {
		if(Ext.isString(this.entityStore)) {
			this.entityStore = go.Stores.get(this.entityStore);
			if(!this.entityStore) {
				throw "Invalid 'entityStore' property given to component"; 
			}
		}
		this.entityStore.on('changes',this.onChanges, this);		
		
		//reload if something goes wrong in the entity store.
		this.entityStore.on("error", this.onError, this);

		this.on('beforedestroy', function() {
			this.entityStore.un('changes', this.onChanges, this);
			this.entityStore.un("error", this.onError, this);
		}, this);
	},
	
	onError : function() {
		this.reload();
	},

	onChanges : function(entityStore, added, changed, destroyed) {		

		if(!this.loaded || this.loading) {
			return;
		}		
		

		if(Object.keys(added).length || Object.keys(changed).length) {
			//we must reload because we don't know how to sort partial data.
			this.reload();
		}
		
//		for(var i in added) {
//			if(!this.updateRecord(added[i]) ){
//				this.reload();
//				return;
//			}
//		}
//				
//		for(var i in changed) {
//			if(!this.updateRecord(changed[i]) ){
//				this.reload();
//				return;
//			}
//		}
		
		for(var i = 0, l = destroyed.length; i < l; i++) {
			var record = this.getById(destroyed[i]);
			if(record) {
				this.remove(record);
			}
		}

	},
	
	updateRecord : function(entity) {
		
		if(!this.data) {
			return false;
		}
		var record = this.getById(entity.id);
		if(!record) {
			return false;
		}
		

//		if(record.isModified()) {
//			alert("Someone modified your record!");
//		}
		
		record.beginEdit();
		this.fields.each(function(field) {
			if(field.name in entity) {
				record.set(field.name, entity[field.name]);
			}
		});		
		
		record.endEdit();
		record.commit();
		
		return true;
	},
	destroy : function() {	
		this.fireEvent('beforedestroy', this);
		
		go.data.Store.superclass.destroy.call(this);
		
		this.fireEvent('destroy', this);
	},
	
	/**
	 * Load entities by id
	 * 
	 * @param {int[]} ids
	 * @returns {void}
	 */
	loadByIds : function(ids) {
		this.entityStore.get(ids, function (items) {
			this.loadData(items);
		}, this);
	}
	
//	onUpdate : function(store, record, operation) {
//		//debugger;
//		if(this.serverUpdate || this.loading) {
//			return;
//		}
//	
//		if(operation != Ext.data.Record.COMMIT) {
//			return;
//		}
//		
//		var p = {};
//		
//		key = record.phantom ? 'create' : 'update';
//		p[key] = {};
//		p[key][record.id] = record.data;
//		
//		store.fields.each(function(field){
//			if(field.submit === false) {
//				delete record.data[field.name];
//			}
//		});
//		
//		this.entityStore.set(p, function (options, success, response) {
//			
//			var saved = (record.phantom ? response.created : response.updated) || {};
//			if (saved[record.id]) {
//
//				//update client id with server id
//				if(record.phantom) {
////					record.id = record.data.id = response.created[record.id].id;
////					console.log(record.id);
////					record.phantom = false;
//						//remove phanto records as ext doesn't support changinhg record id.
//						this.remove(record);
//				}
//
//			} else
//			{
//				//something went wrong
//				var notSaved = (record.phantom ? response.notCreated : response.notUpdated) || {};
//				if (!notSaved[id]) {
//					notSaved[id] = {type: "unknown"};
//				}
//
//				switch (notSaved[id].type) {
//					case "forbidden":
//						Ext.MessageBox.alert(t("Access denied"), t("Sorry, you don't have permissions to update this item"));
//						break;
//
//					default:
//					
//						
//						Ext.MessageBox.alert(t("Error"), t("Sorry, something went wrong. Please try again."));
//						break;
//				}
//			}
//		}, this);
//		
//	}
});
