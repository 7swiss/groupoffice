/* global go */

(function () {
	function fallbackCopyTextToClipboard(text) {
		var textArea = document.createElement("textarea");
		textArea.value = text;
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();

		try {
			var successful = document.execCommand('copy');
			var msg = successful ? 'successful' : 'unsuccessful';
			console.log('Fallback: Copying text command was ' + msg);
		} catch (err) {
			console.error('Fallback: Oops, unable to copy', err);
		}

		document.body.removeChild(textArea);
	}

	go.util = {

		empty: function (v) {

			if (!v)
			{
				return true;
			}
			if (v == '')
			{
				return true;
			}

			if (v == '0')
			{
				return true;
			}

			if (v == 'undefined')
			{
				return true;
			}

			if (v == 'null')
			{
				return true;
			}
			
			if(Ext.isArray(v) && !v.length) {
				return true;
			}
			return false;

		},

		copyTextToClipboard: function (text) {
			if (!navigator.clipboard) {
				fallbackCopyTextToClipboard(text);
				return;
			}
			navigator.clipboard.writeText(text).then(function () {
				console.log('Async: Copying to clipboard was successful!');
			}, function (err) {
				console.error('Async: Could not copy text: ', err);
			});
		},

		/**
		 * Launch email composer
		 * 
		 * @param {Object} config {name: "Merijn" email: "mschering@intermesh.nl", subject: "Hello", body: "Just saying hello!"}
		 * @return {undefined}
		 */
		mailto: function (config) {
			var email = config.email;

			if (config.name) {
				email = '"' + config.name.replace(/"/g, '\"') + '" <' + config.email + '>';
			}

			document.location = "mailto:" + email;
		},

		callto: function (config) {
			document.location = "tel:" + config.number;
		},

		streetAddress: function (config) {
			window.open("https://www.openstreetmap.org/search?query=" + encodeURIComponent(config.street + ", " + config.zipCode.replace(/ /g, '') + ", " + config.country));
		},

		showDate: function (date) {
			console.log("No date handler");
		},
		
		/**
		 * cfg.multiple: boolean allow selecting multi files
		 * cfg.accept: string mime type or file extensions to allow for selection
		 * cfg.directory: boolean allow directory upload
		 * cfg.autoUpload: boolean jmap upload file on select
		 * cfg.listeners: {
		 *   select => (files: File[]): callback to trigger when files are selected
		 *   upload => (response: {blobId: "..."}): response from server after every Upload completed (if autoUpload)
		 *   uploadComplete => () when all uploads are complete (if autoUpload)
		 *   scope: same as in ext
		 *   
		 * @example
		 * 
		 * {
		 * 			iconCls: 'ic-computer',
		 * 			text: t("Upload"),
		 * 			handler: function() {
		 * 				go.util.openFileDialog({
		 * 					multiple: true,
		 * 					accept: "image/*",
		 * 					directory: true,
		 * 					autoUpload: true,
		 * 					listeners: {
		 * 						upload: function(response) {
		 * 							var img = '<img src="' + go.Jmap.downloadUrl(response.blobId) + '" alt="'+response.name+'" />';
		 * 							
		 * 							this.editor.focus();
		 * 							this.editor.insertAtCursor(img);
		 * 						},
		 * 						scope: this
		 * 					}
		 * 				});
		 * 			},
		 * 			scope: this
		 * 		}
		 *   
		 * @param {object} cfg
		 */
		openFileDialog: function(cfg) {
			if (!this.uploadDialog) {
				this.uploadDialog = document.createElement("input");
				this.uploadDialog.setAttribute("type", "file");
				this.uploadDialog.onchange = function (e) {
					
					var uploadCount = this.files.length;
					
					if(!uploadCount) {
						return;
					}
					
					if(this.cfg.listeners.select) { 
						this.cfg.listeners.select.call(this.cfg.listeners.scope||this, this.files); 
					}
					
					if(!this.cfg.autoUpload) {						
						return;
					}
					
					for (var i = 0; i < this.files.length; i++) {
						go.Jmap.upload(this.files[i], {
							success: function(response) {
								if(this.cfg.listeners.upload) {
									this.cfg.listeners.upload.call(this.cfg.listeners.scope||this, response);
								}
								uploadCount--;
								if(uploadCount === 0 && this.cfg.listeners.uploadComplete) {
									this.cfg.listeners.uploadComplete.call(this.cfg.listeners.scope||this);
								}
							},
							scope: this
						});
					}
					
					this.value = "";
				};
			}
			this.uploadDialog.cfg = cfg;
			this.uploadDialog.removeAttribute('webkitdirectory');
			this.uploadDialog.removeAttribute('directory');
			this.uploadDialog.removeAttribute('multiple');
			this.uploadDialog.setAttribute('accept', cfg.accept || '*/*');
			if(cfg.directory) {
				this.uploadDialog.setAttribute('webkitdirectory', true);
				this.uploadDialog.setAttribute('directory', true);
			}
			if(cfg.directory) {
				this.uploadDialog.setAttribute('multiple', true);
			}
			
			this.uploadDialog.click();
		},
		
		textToHtml : function(text) {
			return Ext.util.Format.nl2br(text);
		},
		
		addSlashes : function( str ) {
			return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
		}
		
		
		/*
		 * Search through group-office. 
		 * 
		 * Code can be found in go/modules/core/search/views/extjs3/Module.js
		 * @method search
		 * @param {string} query
		 */

	};



})();
