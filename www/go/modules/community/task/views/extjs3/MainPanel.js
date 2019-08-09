/** 
 * Copyright Intermesh
 * 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 * 
 * If you have questions write an e-mail to info@intermesh.nl
 * 
 * @version $Id: MainPanel.js 19225 2015-06-22 15:07:34Z wsmits $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 */

go.modules.community.task.MainPanel = Ext.extend(go.modules.ModulePanel, {
	title: t("Tasks"),
	layout: 'responsive',
	layoutConfig: {
		triggerWidth: 1000
	},

	initComponent: function () {
		this.createTaskGrid();
		this.createTasklistGrid();	
		this.createCategoriesGrid();

		this.gridPanel = new go.modules.community.task.TaskPanel( {		
			id:'ta-tasks-grid',
			loadMask:true,
			region:'center'
		});

		this.taskDetail = new go.modules.community.task.TaskDetail({
			region: 'center',
			split: true,
			tbar: [{
					cls: 'go-narrow',
					iconCls: "ic-arrow-back",
					handler: function () {
						//this.westPanel.show();
						go.Router.goto("taskstask");
					},
					scope: this
				}]
		});

		var filterPanel = new go.NavMenu({
			region:'north',
			store: new Ext.data.ArrayStore({
				fields: ['name', 'icon', 'inputValue'],
				data: [
					[t("Today", "tasks"), 'content_paste', 'active'],
					[t("Due in seven days", "tasks"), 'filter_7', 'sevendays'],
					[t("Overdue", "tasks"), 'schedule', 'overdue'],
					[t("Incomplete tasks", "tasks"), 'assignment_late', 'incomplete'],
					[t("Completed", "tasks"), 'assignment_turned_in', 'completed'],
					[t("Future tasks", "tasks"), 'assignment_return', 'future'],
					[t("All", "tasks"), 'assignment', 'all'],
				]
			}),
			listeners: {
				selectionchange: function(view, nodes) {
					switch(nodes[0].viewIndex) {
						// tasks today
						case 0:
						var now = new Date(),
						nowYmd = now.format("Y-m-d");
						this.taskGrid.store.setFilter("tasklists", {
							due: nowYmd,
							percentageComplete: 0
						});

						break;
						// ends this week
						case 1:

						var now = new Date();
						var nextWeek = now.add(Date.DAY, 7);

						nowYmd = now.format("Y-m-d");
						nextWeekYmd = nextWeek.format("Y-m-d");

						this.taskGrid.store.setFilter("tasklists", {
							nextweek: nextWeekYmd, 
							percentageComplete: 0
						});

						// this.taskGrid.store.setFilter('tasklists',{
						// 	operator:'AND',
						// 	conditions:[
						// 	//{start:'> '+ nowYmd },
						// 	//{start:'< '+ nowYmd }
						// 	{due: nowYmd}
						// ]});

						break;
						// tasks too late
						case 2:
						var now = new Date(),
						nowYmd = now.format("Y-m-d");
						this.taskGrid.store.setFilter('tasklists',{
							late: nowYmd,
							percentageComplete: 0
						});
						break;
						// non completed tasks
						case 3:
						this.taskGrid.store.setFilter("tasklists", {
							percentageComplete: 0
						});
						break;
						// completed tasks
						case 4:
						this.taskGrid.store.setFilter("tasklists", {
							percentageComplete: 100
						});
						break;
						case 5:
						var now = new Date(),
						nowYmd = now.format("Y-m-d");
						this.taskGrid.store.setFilter('tasklists',{
							future: nowYmd,
							percentageComplete: 0
						});
						break;
						case 6:
						this.taskGrid.store.setFilter("tasklists", null);
						break;
					}
					this.taskGrid.store.load();
					// var record = view.store.getAt(nodes[0].viewIndex);
					// this.gridPanel.store.baseParams['show']=record.data.inputValue;
					// this.gridPanel.store.load();
				},
				scope: this
			}
		});

		this.sidePanel = new Ext.Panel({
			width: dp(300),
			cls: 'go-sidenav',
			region: "west",
			split: true,
			autoScroll: true,			
			items: [
				filterPanel,
				this.TasklistsGrid,
				this.categoriesGrid
				
			]
		});

		this.westPanel = new Ext.Panel({
			region: "west",
			layout: "responsive",
			stateId: "go-tasks-west",
			split: true,
			width: dp(700),
			narrowWidth: dp(400),
			height:dp(800), //this will only work for panels inside another panel with layout=responsive. Not ideal but at the moment the only way I could make it work
			items: [
				this.taskGrid,
				this.sidePanel
			]
		});

		this.items = [
			this.westPanel, //first is default in narrow mode
			this.taskDetail
		];

		go.modules.community.task.MainPanel.superclass.initComponent.call(this);
		this.on("afterrender", this.runModule, this);
		this.taskGrid.store.load();
		this.categoriesGrid.store.load();
	},
	
	runModule : function() {
		//load task lists and select the first
		this.TasklistsGrid.getStore().load({
			callback: function (store) {
				//this.TasklistsGrid.getSelectionModel().selectRow(0);
			},
			scope: this
		});
	},
	
	createCategoriesGrid: function() {
		this.categoriesGrid = new go.modules.community.task.CategoriesGrid({
			region: 'west',
			cls: 'go-sidenav',
			width: dp(280),
			height: dp(350),
			split: true,
			tbar: [{
					xtype: 'tbtitle',
					text: t('Categories',"tasks")
				}, '->', {
					//disabled: go.Modules.get("community", 'notes').permissionLevel < go.permissionLevels.write,
					iconCls: 'ic-add',
					tooltip: t('Add'),
					handler: function (e, toolEl) {
						var dlg = new go.modules.community.task.CategoryDialog();
						dlg.show();
					}
				}, 
				{
					cls: 'go-narrow',
					iconCls: "ic-arrow-forward",
					tooltip: t("Tasks"),
					handler: function () {
						this.categoriesGrid.show();
					},
					scope: this
				}],
			listeners: {
				rowclick: function(grid, row, e) {
					if(e.target.className != 'x-grid3-row-checker') {
						//if row was clicked and not the checkbox then switch to grid in narrow mode
						this.categoriesGrid.show();
					}
				},
				scope: this
			}
		});

		this.categoriesGrid.getSelectionModel().on('selectionchange', this.onCategorySelectionChange, this, {buffer: 1}); //add buffer because it clears selection first
	},
	createTasklistGrid : function() {
		this.TasklistsGrid = new go.modules.community.task.TasklistsGrid({
			region: 'west',
			cls: 'go-sidenav',
			width: dp(280),
			height:dp(350),
			split: true,
			tbar: [{
					xtype: 'tbtitle',
					text: t('Tasklist',"tasks")
				}, '->', {
					//disabled: go.Modules.get("community", 'notes').permissionLevel < go.permissionLevels.write,
					iconCls: 'ic-add',
					tooltip: t('Add'),
					handler: function (e, toolEl) {
						var dlg = new go.modules.community.task.TasklistDialog();
						dlg.show();
					}
				}, 
				{
					cls: 'go-narrow',
					iconCls: "ic-arrow-forward",
					tooltip: t("Tasks"),
					handler: function () {
						this.taskGrid.show();
					},
					scope: this
				}],
			listeners: {
				rowclick: function(grid, row, e) {
					if(e.target.className != 'x-grid3-row-checker') {
						//if row was clicked and not the checkbox then switch to grid in narrow mode
						this.taskGrid.show();
					}
				},
				scope: this
			}
		});

		this.TasklistsGrid.getSelectionModel().on('selectionchange', this.onTasklistSelectionChange, this, {buffer: 1}); //add buffer because it clears selection first
	},
	
	
	createTaskGrid : function() {
		this.taskGrid = new go.modules.community.task.TaskGrid({
			layout:'fit',
			region: 'center',
			tbar: [
				{
					cls: 'go-narrow',
					iconCls: "ic-menu",
					handler: function () {
//						this.westPanel.getLayout().setActiveItem(this.noteBookGrid);
						this.TasklistsGrid.show();
					},
					scope: this
				},
				'->',
				{
					xtype: 'tbsearch',
					filters: [
						'text',
						'title', 
						'content',
						{name: 'modified', multiple: false},
						{name: 'created', multiple: false}						
					]
				},
				this.addButton = new Ext.Button({
					disabled: true,
					iconCls: 'ic-add',
					tooltip: t('Add'),
					handler: function (btn) {
						var dlg = new go.modules.community.task.TaskDialog();
						dlg.show();
						dlg.setValues({
								tasklistId: this.addTasklistId
						});
					},
					scope: this
				}),
				{
					iconCls: 'ic-more-vert',
					menu: [
						{
							itemId: "delete",
							iconCls: 'ic-delete',
							text: t("Delete"),
							handler: function () {
								this.taskGrid.deleteSelected();
							},
							scope: this
						},
						{
							iconCls: 'ic-refresh',
							tooltip: t("Refresh"),
							text: t("Refresh"),
							handler: function(){
								this.taskGrid.store.load();
								this.categoriesGrid.store.load();
							},
							scope: this
						}
					]
				}
				
			],
			listeners: {				
				rowdblclick: this.onTaskGridDblClick,
				scope: this,				
				keypress: this.onTaskGridKeyPress
			}
		});

		this.taskGrid.on('navigate', function (grid, rowIndex, record) {
			go.Router.goto("taskstask/" + record.id);
		}, this);
		
	
	},
	
	onTasklistSelectionChange : function (sm) {
		var ids = [];

		this.addTasklistId = false;

		Ext.each(sm.getSelections(), function (r) {
			ids.push(r.id);
			if (!this.addTasklistId && r.json.permissionLevel >= go.permissionLevels.write) {
			// is dit goed? r.get('permissionLevel')
			// if (!this.addTasklistId && r.get('permissionLevel') >= go.permissionLevels.write) {
				this.addTasklistId = r.id;
			}
		}, this);

		this.addButton.setDisabled(!this.addTasklistId);
		this.taskGrid.store.setFilter("tasklists", {tasklistId: ids});
		this.taskGrid.store.load();
		this.categoriesGrid.store.load();
	},
	onCategorySelectionChange : function (sm) {
		var ids = [];

		this.categoryId = false;

		Ext.each(sm.getSelections(), function (r) {
			ids.push(r.id);
			if (!this.addTasklistId && r.json.permissionLevel >= go.permissionLevels.write) {
			// is dit goed? r.get('permissionLevel')
			// if (!this.addTasklistId && r.get('permissionLevel') >= go.permissionLevels.write) {
				this.categoryId = r.id;
			}
		}, this);

		this.taskGrid.store.setFilter("categories", {categories: ids});
		this.taskGrid.store.load();
		this.categoriesGrid.store.load();
	},
	
	onTaskGridDblClick : function (grid, rowIndex, e) {

		var record = grid.getStore().getAt(rowIndex);
		if (record.get('permissionLevel') < go.permissionLevels.write) {
			return;
		}

		var dlg = new go.modules.community.task.TaskDialog();
		dlg.load(record.id).show();
	},
	
	onTaskGridKeyPress : function(e) {
		if(e.keyCode != e.ENTER) {
			return;
		}
		var record = this.taskGrid.getSelectionModel().getSelected();
		if(!record) {
			return;
		}

		if (record.get('permissionLevel') < go.permissionLevels.write) {
			return;
		}

		var dlg = new go.modules.community.task.TaskDialog();
		dlg.load(record.id).show();
	}	
});

