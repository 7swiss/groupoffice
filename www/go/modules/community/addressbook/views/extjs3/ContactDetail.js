go.modules.community.addressbook.ContactDetail = Ext.extend(go.panels.DetailView, {
	entityStore: go.Stores.get("Contact"),
	stateId: 'addressbook-contact-detail',
	
	initComponent: function () {


		this.tbar = this.initToolbar();

		Ext.apply(this, {
			items: [{
					xtype: 'container',
					layout: "hbox",
					cls: "go-addressbook-name-panel",
					items: [						
						this.namePanel = new Ext.BoxComponent({
							tpl: "<h3>{name}</h3><h4>{jobTitle}</h4>"
						}),
						this.starButton = new go.modules.community.addressbook.StarButton(), 
						this.urlPanel = new Ext.BoxComponent({
							flex: 1,
							cls: 'go-addressbook-url-panel',
							xtype: "box",
							tpl: '<tpl for=".">&nbsp;&nbsp;<a target="_blank" href="{url}" class="go-addressbook-url {type}"></a></tpl>'
						})
					],
					onLoad: function (detailView) {
						detailView.data.jobTitle = detailView.data.jobTitle || "";						
						detailView.namePanel.update(detailView.data);
						detailView.urlPanel.update(detailView.data.urls);
						detailView.starButton.setContactId(detailView.data.id);
					},
					
				}, 
				
				{
					tpl: new Ext.XTemplate('<div class="go-detail-view-avatar">\
<div class="avatar {[this.getCls(values.isOrganization)]}" style="{[this.getStyle(values.photoBlobId)]}"></div></div>', 
					{
						getCls: function (isOrganization) {
							return isOrganization ? "organization" : "";
						},
						getStyle: function (photoBlobId) {
							return photoBlobId ? 'background-image: url(' + go.Jmap.downloadUrl(photoBlobId) + ')"' : "";
						}
					})
				},
				
				
				{
					onLoad: function(dv) {
						dv.emailButton.menu.removeAll();						
						dv.data.emailAddresses.forEach(function(a) {
							dv.emailButton.menu.addMenuItem({
								text: "<div>" + a.email + "</div><small>" + t("emailTypes")[a.type] + "</small></div>",
								handler: function() {
									go.util.mailto({
										email: a.email,
										name: dv.name
									});
								}
							});
						});
						dv.emailButton.setDisabled(dv.data.emailAddresses.length == 0);
						
						
						dv.callButton.menu.removeAll();						
						dv.data.phoneNumbers.forEach(function(a) {
							dv.callButton.menu.addMenuItem({
								text: "<div>" + a.number + "</div><small>" + t("phoneTypes")[a.type] + "</small></div>",
								handler: function() {
									go.util.callto({
										number: a.number,
										name: dv.name
									});
								}
							});
						});
						dv.callButton.setDisabled(dv.data.phoneNumbers.length == 0);
						
					},
					xtype: "toolbar",
					cls: "actions",
					buttonAlign: "center",
					items: [
						this.emailButton = new Ext.Button({
							menu: {cls: "x-menu-no-icons", items: []},
							text: t("E-mail"),
							iconCls: 'ic-email',
							disabled: true
						}),
						
						this.callButton = new Ext.Button({
							menu: {cls: "x-menu-no-icons", items: []},
							text: t("Call"),
							iconCls: 'ic-phone',
							disabled: true
						})
					]
				},{
					xtype: "box",
					listeners: {
						scope: this,
						afterrender: function(box) {
							
							box.getEl().on('click', function(e){								
								var container = box.getEl().dom.firstChild, 
								item = e.getTarget("a", box.getEl()),
								i = Array.prototype.indexOf.call(container.getElementsByTagName("a"), item);
								
								go.util.streetAddress(this.data.addresses[i]);
							}, this);
						}
					},
					tpl: '<div class="icons">\
					<tpl for="addresses">\
						<hr class="indent">\
						<a class="s6"><i class="icon label">location_on</i>\
							<span>{street}<br>\
							<tpl if="zipCode">{zipCode}<br></tpl>\
							<tpl if="city">{city}<br></tpl>\
							<tpl if="state">{state}<br></tpl>\
							<tpl if="country">{country}</tpl></span>\
							<label>{[t("addressTypes")[values.type]]}</label>\
						</a>\
					</tpl>\
					</div>'
				}, {
					xtype: "box",
					listeners: {
						scope: this,
						afterrender: function(box) {
							
							box.getEl().on('click', function(e){								
								var container = box.getEl().dom.firstChild, 
								item = e.getTarget("a", box.getEl()),
								i = Array.prototype.indexOf.call(container.getElementsByTagName("a"), item);
								
								go.util.showDate(this.data.dates[i]);
							}, this);
						}
					},
					tpl: '<tpl if="dates.length">\
						<div class="icons">\
						<hr class="indent">\
						<tpl for="dates"><a class="s6"><tpl if="xindex == 1"><i class="icon label">cake</i></tpl>\
							<span>{[GO.util.dateFormat(values.date)]}</span>\
							<label>{[t("dateTypes")[values.type]]}</label>\
						</a></tpl>\
					</div>\
					</tpl>'
				}, {
					collapsible: true,
					title: t("Organizations"),
					xtype: "panel",
					onLoad : function(dv) {
						this.setVisible(dv.data.organizations.length);
						if(!dv.data.organizations.length) {							
							return;
						}
						
						if(!this.template) {
							this.template = new Ext.XTemplate('<div class="icons">\
					<tpl for=".">\
						<a class="s6" href="#contact/{id}"><tpl if="xindex == 1"><i class="icon label">domain</i></tpl>\
							<span>{name}</span>\
							<label>{[values.jobTitle || "-"]}</label>\
						</a>\
					</tpl>\
					</div>').compile();
						}
						
						var ids = dv.data.organizations.column('organizationContactId');
						
						go.Stores.get("Contact").get(ids, function(organizations) {
							this.update(this.template.apply(organizations));
						}, this);
					}
				}
			]
		});


		go.modules.community.addressbook.ContactDetail.superclass.initComponent.call(this);

		//go.CustomFields.addDetailPanels(this);

		this.add(new go.links.LinksDetailPanel());

		if (go.Modules.isAvailable("legacy", "comments")) {
			this.add(new go.modules.comments.CommentsDetailPanel());
		}

		if (go.Modules.isAvailable("legacy", "files")) {
			this.add(new go.modules.files.FilesDetailPanel());
		}
	},

	onLoad: function () {

		this.getTopToolbar().getComponent("edit").setDisabled(this.data.permissionLevel < GO.permissionLevels.write);

		go.modules.community.addressbook.ContactDetail.superclass.onLoad.call(this);
	},

	initToolbar: function () {

		var items = this.tbar || [];

		items = items.concat([
			'->',
			{
				itemId: "edit",
				iconCls: 'ic-edit',
				tooltip: t("Edit"),
				handler: function (btn, e) {
					var dlg = new go.modules.community.addressbook.ContactDialog();
					dlg.show();
					dlg.load(this.data.id);
				},
				scope: this
			},

			new go.detail.addButton({
				detailPanel: this
			}),

			{
				iconCls: 'ic-more-vert',
				menu: [
					{
						iconCls: "btn-print",
						text: t("Print"),
						handler: function () {
							this.body.print({title: this.data.name});
						},
						scope: this
					}

				]
			}]);

		var tbarCfg = {
			disabled: true,
			items: items
		};


		return new Ext.Toolbar(tbarCfg);


	}
});
