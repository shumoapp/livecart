/**
 *	@author Integry Systems
 */

/**
 *	Requires rico.js
 *
 */

ActiveGrid = Class.create();

ActiveGrid.prototype =
{
	/**
	 *	Data table element instance
	 */
	tableInstance: null,

	/**
	 *	Select All checkbox instance
	 */
	selectAllInstance: null,

	/**
	 *	Data feed URL
	 */
  	dataUrl: null,

	/**
	 *	Rico LiveGrid instance
	 */
	ricoGrid: null,

	/**
	 *	Array containing IDs of selected rows
	 */
	selectedRows: {},

	/**
	 *	Set to true when Select All is used (so all records are selected by default)
	 */
	inverseSelection: false,

	/**
	 *	Object that handles data transformation for presentation
	 */
	dataFormatter: null,

	filters: {},

	loadIndicator: null,

	rowCount: 15,

	quickEditUrlTemplate: null,

	quickEditIdToken : null,

	activeGridInstanceID : null,

	initialize: function(tableInstance, dataUrl, totalCount, loadIndicator, rowCount, filters)
	{
		this.tableInstance = tableInstance;
		this.activeGridInstanceID=this.tableInstance.id;
		this.tableInstance.gridInstance = this;
		this.dataUrl = dataUrl;
		this.setLoadIndicator(loadIndicator);
		this.filters = {};
		this.selectedRows = {};

		if (!rowCount)
		{
			rowCount = this.rowCount;
		}

		if (filters)
		{
			this.filters = filters;
		}

		this.ricoGrid = new Rico.LiveGrid(this.tableInstance.id, rowCount, totalCount, dataUrl,
								{
								  prefetchBuffer: true,
								  onscroll: this.onScroll.bind(this),
								  sortAscendImg: $("bullet_arrow_up").src,
								  sortDescendImg: $("bullet_arrow_down").src
								}
							);

		this.ricoGrid.activeGrid = this;

		var headerRow = this._getHeaderRow();
		this.selectAllInstance = headerRow.down('input');
		this.selectAllInstance.onclick = this.selectAll.bindAsEventListener(this);
		this.selectAllInstance.parentNode.onclick = function(e){Event.stop(e);}.bindAsEventListener(this);

		this.ricoGrid.onUpdate = this.onUpdate.bind(this);
		this.ricoGrid.onBeginDataFetch = this.showFetchIndicator.bind(this);
		this.ricoGrid.options.onRefreshComplete = this.hideFetchIndicator.bind(this);

		this.onScroll(this.ricoGrid, 0);

		this.setRequestParameters();
		this.ricoGrid.init();

		var rows = this.tableInstance.down('tbody').getElementsByTagName('tr');
		for (k = 0; k < rows.length; k++)
		{
			Event.observe(rows[k], 'click', this.selectRow.bindAsEventListener(this));

			var cells = rows[k].getElementsByTagName('td');
			for (i = 0; i < cells.length; i++)
			{
				Event.observe(cells[i], 'mouseover', this.highlightRow.bindAsEventListener(this));
			}

			Event.observe(rows[k], 'mouseout', this.removeRowHighlight.bindAsEventListener(this));
		}
	},

	initQuickEdit: function(urlTemplate, idToken)
	{
		this.quickEditUrlTemplate = urlTemplate;
		this.quickEditIdToken = idToken;
		Event.observe(this.tableInstance.down('tbody'), 'mouseover', this.quickEdit.bindAsEventListener(this) );
	},

	quickEdit: function(event)
	{
		var
			node = Event.element(event),
			recordID = null,
			m;

		if (node.tagName.toLowerCase != "tr")
		{
			node=node.up("tr");
		}
		var pos = Position.cumulativeOffset(node);
		mh = new PopupMenuHandler(pos[0], pos[1], 200, 200);
		do {
			m = node.down("input").name.match(/item\[(\d+)\]/);
			if (m && m.length == 2)
			{
				recordID = m[1];
			}
			else
			{
				node=$(node.up("tr"));
			}
		} while(recordID == null && node);

		if (recordID == null)
		{
			return;
		}

		this.node = node;

		new LiveCart.AjaxRequest(
			this.quickEditUrlTemplate.replace(this.quickEditIdToken, recordID),
			null,
			function(transport)
			{
				var container = this.instance._getQuickEditContainer();
				if(container)
				{
					container.innerHTML = transport.responseText;
					//console.log(this.mh.y);
					container.style.top=(this.mh.y-230)+"px";
					container.style.left=(20)+"px";
					container.show();
				}

			}.bind({instance:this, mh:mh})
		)
	},

	hideQuickEditContainer : function()
	{
		var container=this._getQuickEditContainer().hide();
		container.innerHTML = "";
		container.hide();
	},

	updateQuickEditGrid: function(jsonData)
	{
		var
			buffer = this.ricoGrid.buffer,
			i,
			rows,
			row,
			done = false;

		rows = this.getRows(jsonData);
		row = rows.data[0];

		for(i=0; i<buffer.rows.length; i++)
		{
			if(row.ID == buffer.rows[i].id)
			{
				buffer.rows[i] = row;
				break;
			}
		}
		for(page in buffer.rowCache)
		{
			if(done)
			{
				break;
			}
			for(rowNr in buffer.rowCache[page])
			{
				if("id" in buffer.rowCache[page][rowNr] == false)
				{
					continue;
				}
				if(done)
				{
					break;
				}

				if(buffer.rowCache[page][rowNr].id == row.id)
				{
					buffer.rowCache[page][rowNr] = row;
					done=true;
				}
			}
		}

		// redraw grid
		this.ricoGrid.viewPort.bufferChanged();
		this.ricoGrid.viewPort.refreshContents(this.ricoGrid.viewPort.lastRowPos);

		$A(document.getElementsByName("item["+row.id+"]")).each(function(input) {
			new Effect.Highlight($(input).up("tr"));
		});
	},

	_getQuickEditContainer: function()
	{
		var parent = $(this.tableInstance), node=null, i=0;

		while (i<25 && parent && parent.hasClassName("activeGridContainer") == false)
		{
			i++;
			parent = $(parent.up("div"));
		}
		if (parent)
		{
			node = parent.getElementsByClassName("quickEditContainer");
		}

		if(node == null || node.length!=1)
		{
			console.log('QW container not found');
			return null;
		}
		return $(node[0]);
	},

	setInitialData: function(data)
	{
		if (data)
		{
			this.ricoGrid.buffer.update(data, 0);
			this.ricoGrid.viewPort.bufferChanged();
			this.ricoGrid.viewPort.refreshContents(0);
		}
		else
		{
			this.ricoGrid.requestContentRefresh(0);
		}
	},

	getRows: function(data)
	{
		var HTML = '';
		var rowHTML = '';

		var data = eval('(' + data + ')');

		for(k = 0; k < data['data'].length; k++)
		{
			var id = data['data'][k][0];

			data['data'][k][0] = '<input type="checkbox" class="checkbox" name="item[' + id + ']" />';
			data['data'][k].id = id;

			if (this.dataFormatter)
			{
				for(i = 1; i < data['data'][k].length; i++)
				{
					if(i > 0)
					{
						data['data'][k][i] = stripHtml(data['data'][k][i]);
					}

					var filter = this.filters['filter_' + data['columns'][i]];
					if (filter && data['data'][k][i].replace)
					{
						data['data'][k][i] = data['data'][k][i].replace(new RegExp('(' + filter + ')', 'gi'), '<span class="activeGrid_searchHighlight">$1</span>');
					}

					var value = this.dataFormatter.formatValue(data['columns'][i], data['data'][k][i], id) || '';
					data['data'][k][i] = '<span>' + value + '</span>';
				}
			}
		}

		return data;
	},

	setDataFormatter: function(dataFormatterInstance)
	{
		this.dataFormatter = dataFormatterInstance;
	},

	setLoadIndicator: function(indicatorElement)
	{
		this.loadIndicator = $(indicatorElement);
	},

	onScroll: function(liveGrid, offset)
	{
		this.ricoGrid.onBeginDataFetch = this.showFetchIndicator.bind(this);

		this.updateHeader(offset);

		this._markSelectedRows();
	},

	updateHeader: function (offset)
	{
		var liveGrid = this.ricoGrid;

		var totalCount = liveGrid.metaData.getTotalRows();
		var from = offset + 1;
		var to = offset + liveGrid.metaData.getPageSize();

		if (to > totalCount)
		{
			to = totalCount;
		}

		if (!this.countElement)
		{
			this.countElement = this.loadIndicator.parentNode.up('div').down('.rangeCount');
			this.notFound = this.loadIndicator.parentNode.up('div').down('.notFound');
		}

		if (!this.countElement)
		{
			return false;
		}

		if (totalCount > 0)
		{
			if (!this.countElement.strTemplate)
			{
				this.countElement.strTemplate = this.countElement.innerHTML;
			}

			var str = this.countElement.strTemplate;
			str = str.replace(/\$from/, from);
			str = str.replace(/\$to/, to);
			str = str.replace(/\$count/, totalCount);

			this.countElement.innerHTML = str;
			this.notFound.style.display = 'none';
			this.countElement.style.display = '';
		}
		else
		{
			this.notFound.style.display = '';
			this.countElement.style.display = 'none';
		}
	},

	onUpdate: function()
	{
		this._markSelectedRows();
	},

	setRequestParameters: function()
	{
		this.ricoGrid.options.requestParameters = [];
		var i = 0;

		for (k in this.filters)
		{
			if (k.substr(0, 7) == 'filter_')
			{
				this.ricoGrid.options.requestParameters[i++] = 'filters[' + k.substr(7, 1000) + ']' + '=' + encodeURIComponent(this.filters[k]);
			}
		}
	},

	reloadGrid: function()
	{
		this.setRequestParameters();

		this.ricoGrid.buffer.clear();
		this.ricoGrid.resetContents();
		this.ricoGrid.requestContentRefresh(0, true);
		this.ricoGrid.fetchBuffer(0, false, true);

		this._markSelectedRows();
	},

	getFilters: function()
	{
		var res = {};

		for (k in this.filters)
		{
			if (k.substr(0, 7) == 'filter_')
			{
				res[k.substr(7, 1000)] = this.filters[k];
			}
		}

		return res;
	},

	getSelectedIDs: function()
	{
		var selected = [];

		for (k in this.selectedRows)
		{
			if (true == this.selectedRows[k])
			{
				selected[selected.length] = k;
			}
		}

		return selected;
	},

	isInverseSelection: function()
	{
		return this.inverseSelection;
	},

	/**
	 *	Select all rows
	 */
	selectAll: function(e)
	{
		this.selectedRows = new Object;
		this.inverseSelection = this.selectAllInstance.checked;
		this._markSelectedRows();

		e.stopPropagation();
	},

	/**
	 *	Mark rows checkbox when a row is clicked
	 */
	selectRow: function(e)
	{
		var row = this._getTargetRow(e);

		id = this._getRecordId(row);

		if (!this.selectedRows[id])
		{
			this.selectedRows[id] = 0;
		}

		this.selectedRows[id] = !this.selectedRows[id];

		this._selectRow(row);
	},

	/**
	 *	Highlight a row when moving a mouse over it
	 */
	highlightRow: function(event)
	{
		var cell = this._getTargetCell(event);
		var row = cell ? cell.parentNode : this._getTargetRow(event);
		Element.addClassName(row, 'activeGrid_highlight');

		if (cell)
		{
			var value = cell.down('span');
			if (value && value.offsetWidth > cell.offsetWidth)
			{
				if (!this.cellContentContainer)
				{
					var cont = cell.up('.activeGridContainer');
					this.cellContentContainer = cont.down('.activeGridCellContent');
				}

				var xPos = Event.pointerX(event) - 50 - window.scrollX;
				var yPos = Event.pointerY(event) + 25 - window.scrollY;
				this.cellContentContainer.innerHTML = value.innerHTML;

				// remove progress indicator
				var pI = this.cellContentContainer.down('.progressIndicator');
				if (pI)
				{
					pI.parentNode.removeChild(pI);
				}

				this.cellContentContainer.style.visibility = 'none';
				this.cellContentContainer.style.display = 'block';

				PopupMenuHandler.prototype.getByElement(this.cellContentContainer, xPos, yPos);

				this.cellContentContainer.style.visibility = 'visible';
			}
		}
	},

	/**
	 *	Remove row highlighting when mouse is moved out of the row
	 */
	removeRowHighlight: function(event)
	{
		if (this.cellContentContainer)
		{
			// hide() not used intentionally
			this.cellContentContainer.style.display = 'none';
		}

		Element.removeClassName(this._getTargetRow(event), 'activeGrid_highlight');
	},

	setFilterValue: function(key, value)
	{
		this.filters[key] = value;
	},

	getFilterValue: function(key)
	{
		return this.filters[key];
	},

	showFetchIndicator: function()
	{
		this.loadIndicator.style.display = '';
		this.loadIndicator.parentNode.up('div').down('.notFound').hide();
	},

	hideFetchIndicator: function()
	{
		this.loadIndicator.style.display = 'none';
	},

	resetSelection: function()
	{
		this.selectedRows = new Object;
		this.inverseSelection = false;
	},

	_markSelectedRows: function()
	{
		var rows = this.tableInstance.getElementsByTagName('tr');
		for (k = 0; k < rows.length; k++)
		{
			this._selectRow(rows[k]);
		}
	},

	_selectRow: function(rowInstance)
	{
		var id = this._getRecordId(rowInstance);

		if (!rowInstance.checkBox)
		{
			rowInstance.checkBox = rowInstance.down('input');
		}

		if (rowInstance.checkBox)
		{
			var checked = this.selectedRows[id];
			if (this.inverseSelection)
			{
				checked = !checked;
			}

			rowInstance.checkBox.checked = checked;

			if (checked)
			{
				rowInstance.addClassName('selected');
			}
			else
			{
				rowInstance.removeClassName('selected');
			}
		}
	},

	_getRecordId: function(rowInstance)
	{
		return rowInstance.recordId;
	},

	/**
	 *	Return event target row element
	 */
	_getTargetRow: function(event)
	{
		return Event.element(event).up('tr');
	},

	/**
	 *	Return event target cell element
	 */
	_getTargetCell: function(event)
	{
		return Event.element(event).up('td');
	},

	_getHeaderRow: function()
	{
		return this.tableInstance.down('tr');
	}
}

ActiveGridFilter = Class.create();

ActiveGridFilter.prototype =
{
	element: null,

	activeGridInstance: null,

	focusValue: null,

	initialize: function(element, activeGridInstance)
	{
		this.element = element;
		this.activeGridInstance = activeGridInstance;
		this.element.onclick = Event.stop.bindAsEventListener(this);
		this.element.onfocus = this.filterFocus.bindAsEventListener(this);
		this.element.onblur = this.filterBlur.bindAsEventListener(this);
		this.element.onchange = this.setFilterValue.bindAsEventListener(this);
		this.element.onkeyup = this.checkExit.bindAsEventListener(this);

		this.element.filter = this;

   		Element.addClassName(this.element, 'activeGrid_filter_blur');

		this.element.columnName = this.element.value;
	},

	filterFocus: function(e)
	{
		if (this.element.value == this.element.columnName)
		{
			this.element.value = '';
		}

		this.focusValue = this.element.value;

  		Element.removeClassName(this.element, 'activeGrid_filter_blur');
		Element.addClassName(this.element, 'activeGrid_filter_select');

		Element.addClassName(this.element.up('th'), 'activeGrid_filter_select');

		Event.stop(e);
	},

	filterBlur: function()
	{
		if ('' == this.element.value.replace(/ /g, ''))
		{
			// only update filter value if it actually has changed
			if ('' != this.focusValue)
			{
				this.setFilterValue();
			}

			this.element.value = this.element.columnName;
		}

		if (this.element.value == this.element.columnName)
		{
			Element.addClassName(this.element, 'activeGrid_filter_blur');
			Element.removeClassName(this.element, 'activeGrid_filter_select');
			Element.removeClassName(this.element.up('th'), 'activeGrid_filter_select');
		}
	},

	/**
	 *  Clear filter value on ESC key
	 */
	checkExit: function(e)
	{
		if (27 == e.keyCode || (13 == e.keyCode && !this.element.value))
		{
			this.element.value = '';

			if (this.activeGridInstance.getFilterValue(this.getFilterName()))
			{
				this.setFilterValue();
				this.filterBlur();
			}

			this.element.blur();
		}

		else if (13 == e.keyCode)
		{
			this.filterBlur();
			this.setFilterValue();
		}
	},

	setFilterValue: function()
	{
		this.setFilterValueManualy(this.getFilterName(), this.element.value);
	},

	setFilterValueManualy: function(filterName, value)
	{
		this.activeGridInstance.setFilterValue(filterName, value);
		this.activeGridInstance.reloadGrid();
	},

	getFilterName: function()
	{
		return this.element.id.substr(0, this.element.id.indexOf('_', 7));
	},

	initFilter: function(e)
	{
		Event.stop(e);

		var element = Event.element(e);
		if ('LI' != element.tagName && element.up('li'))
		{
			element = element.up('li');
		}

		this.filterFocus(e);

		if (element.attributes.getNamedItem('symbol'))
		{
			this.element.value = element.attributes.getNamedItem('symbol').nodeValue;
		}

		// range fields
		var cont = element.up('th');
		var min = cont.down('.min');
		var max = cont.down('.max');

		// show/hide input fields
		if ('><' == this.element.value)
		{
			Element.hide(this.element);
			Element.show(this.element.next('div.rangeFilter'));
			min.focus();
		}
		else
		{
			Element.show(this.element);
			Element.hide(this.element.next('div.rangeFilter'));

			min.value = '';
			max.value = '';
			this.element.focus();

			if ('' == this.element.value)
			{
				this.element.blur();
				this.setFilterValue();
			}
		}

		// hide menu
		if (element.up('div.filterMenu'))
		{
			Element.hide(element.up('div.filterMenu'));
			window.setTimeout(function() { Element.show(this.up('div.filterMenu')); }.bind(element), 200);
		}
	},

	updateRangeFilter: function(e)
	{
		var cont = Event.element(e).up('div.rangeFilter');
		var min = cont.down('.min');
		var max = cont.down('.max');

		if ((parseInt(min.value) > parseInt(max.value)) && max.value.length > 0)
		{
			var temp = min.value;
			min.value = max.value;
			max.value = temp;
		}

		this.element.value = (min.value.length > 0 ? '>=' + min.value + ' ' : '') + (max.value.length > 0 ? '<=' + max.value : '');

		this.element.filter.setFilterValue();

		if ('' == this.element.value)
		{
			this.initFilter(e);
		}
	}
}

ActiveGrid.MassActionHandler = Class.create();
ActiveGrid.MassActionHandler.prototype =
{
	handlerMenu: null,
	actionSelector: null,
	valueEntryContainer: null,
	form: null,
	button: null,
	cancelLink: null,
	cancelUrl: '',

	grid: null,
	pid: null,

	initialize: function(handlerMenu, activeGrid, params)
	{
		this.handlerMenu = handlerMenu;
		this.actionSelector = handlerMenu.down('select');
		this.valueEntryContainer = handlerMenu.down('.bulkValues');
		this.form = this.actionSelector.form;
		this.form.handler = this;
		this.button = this.form.down('.submit');

		Event.observe(this.actionSelector, 'change', this.actionSelectorChange.bind(this));
		Event.observe(this.actionSelector.form, 'submit', this.submit.bindAsEventListener(this));

		this.grid = activeGrid;
		this.params = params;
		this.paramz = params;
	},

	actionSelectorChange: function()
	{
		if (!this.valueEntryContainer)
		{
			return false;
		}

		for (k = 0; k < this.valueEntryContainer.childNodes.length; k++)
		{
			if (this.valueEntryContainer.childNodes[k].style)
			{
				Element.hide(this.valueEntryContainer.childNodes[k]);
			}
		}

		Element.show(this.valueEntryContainer);

		if (this.actionSelector.form.elements.namedItem(this.actionSelector.value))
		{
			var el = this.form.elements.namedItem(this.actionSelector.value);
			if (el)
			{
				Element.show(el);
				this.form.elements.namedItem(this.actionSelector.value).focus();
			}
		}
		else if (document.getElementsByClassName(this.actionSelector.value, this.handlerMenu))
		{
			var el = document.getElementsByClassName(this.actionSelector.value, this.handlerMenu)[0];
			if (el)
			{
				Element.show(el);
			}
		}
	},

	submit: function(e)
	{
		if (e)
		{
			Event.stop(e);
		}

		if ('delete' == this.actionSelector.value)
		{
			if (!confirm(this.deleteConfirmMessage))
			{
				return false;
			}
		}

		var filters = this.grid.getFilters();
		this.form.elements.namedItem('filters').value = filters ? Object.toJSON(filters) : '';
		this.form.elements.namedItem('selectedIDs').value = Object.toJSON(this.grid.getSelectedIDs());
		this.form.elements.namedItem('isInverse').value = this.grid.isInverseSelection() ? 1 : 0;

		if ((0 == this.grid.getSelectedIDs().length) && !this.grid.isInverseSelection())
		{
			this.blurButton();
			alert(this.nothingSelectedMessage);
			return false;
		}

		var indicator = this.handlerMenu.down('.massIndicator');
		if (!indicator)
		{
			indicator = this.handlerMenu.down('.progressIndicator');
		}

		this.formerLength = 0;

		if ('blank' == this.actionSelector.options[this.actionSelector.selectedIndex].getAttribute('rel'))
		{
			this.form.target = '_blank';
			this.form.submit();
			return;
		}

		this.request = new LiveCart.AjaxRequest(this.form, indicator , this.dataResponse.bind(this),  {onInteractive: this.dataResponse.bind(this) });

		this.progressBarContainer = this.handlerMenu.up('div').down('.activeGrid_massActionProgress');
		this.cancelLink = this.progressBarContainer.down('a.cancel');
		this.cancelUrl = this.cancelLink.href;
		this.cancelLink.onclick = this.cancel.bind(this);

		this.progressBarContainer.show();
		this.progressBar = new Backend.ProgressBar(this.progressBarContainer);

		this.grid.resetSelection();
	},

	dataResponse: function(originalRequest)
	{
		var response = originalRequest.responseText.substr(this.formerLength + 1);
		this.formerLength = originalRequest.responseText.length;

		var portions = response.split('|');

		for (var k = 0; k < portions.length; k++)
		{
			if (!portions[k])
			{
				continue;
			}

			if ('}' == portions[k].substr(-1))
			{
				if ('{' != portions[k].substr(0, 1))
				{
					portions[k] = '{' + portions[k];
				}

				this.submitCompleted(eval('(' + portions[k] + ')'));

				return;
			}

			response = eval('(' + decode64(portions[k]) + ')');

			// progress
			if (response.progress != undefined)
			{
				this.progressBar.update(response.progress, response.total);
				this.pid = response.pid;
			}
		}
	},

	cancel: function(e)
	{
		this.request.request.transport.abort();
		new LiveCart.AjaxRequest(Backend.Router.setUrlQueryParam(this.cancelUrl, 'pid', this.pid), null, this.completeCancel.bind(this));
		Event.stop(e);
	},

	completeCancel: function(originalRequest)
	{
		var resp = originalRequest.responseData;

		if (resp.isCancelled)
		{
			var progress = this.progressBar.getProgress();
			this.cancelLink.hide();
			this.progressBar.rewind(progress, this.progressBar.getTotal(), Math.round(progress/50), this.submitCompleted.bind(this));
		}
	},

	submitCompleted: function(responseData)
	{
		if (responseData)
		{
			this.request.showConfirmation(responseData);
		}

		this.progressBarContainer.hide();
		this.cancelLink.show();

		this.grid.reloadGrid();
		this.blurButton();

		if (this.params && this.params.onComplete)
		{
			this.params.onComplete();
		}

		if (this.customComplete)
		{
			this.customComplete();
		}
	},

	blurButton: function()
	{
		this.button.disable();
		this.button.enable();
	}
}

ActiveGrid.QuickEdit =
{
	onSubmit: function(obj)
	{
		var form;
		form = $(obj).up("form");
		if(validateForm(form))
		{
			new LiveCart.AjaxRequest(form, null, function(transport) {
				var response = eval( "("+transport.responseText + ")");
				if(response.status == "success")
				{
					this.instance._getGridInstaceFromControl(this.obj).updateQuickEditGrid(transport.responseText);
					this.instance.onCancel(this.obj);
				}
				else
				{
					ActiveForm.prototype.setErrorMessages(this.obj.up("form"), response.errors)
				}
			}.bind({instance: this, obj:obj}));
		}
		return false;
	},

	onCancel: function(obj)
	{
		var gridInstance = this._getGridInstaceFromControl(obj);
		gridInstance.hideQuickEditContainer();
		return false;
	},

	_getGridInstaceFromControl: function(control)
	{
		try {
			// up 3 div's, then get all elements with class name activeGrid,
			// first table should be grid instance.
			// This works for current uses, some future cases may require to rewrite this function.
			return $A($(control).up("div",3).getElementsByClassName("activeGrid")).find(
				function(node)
				{
					return node.tagName.toLowerCase() == "table";
				}
			).gridInstance;

		} catch(e) {
			return null;
		}
	}
}

function RegexFilter(element, params)
{
	var regex = new RegExp(params['regex'], 'gi');
	element.value = element.value.replace(regex, '');
}

function stripHtml(value)
{
	if (!value || !value.replace)
	{
		return value;
	}

	return value.replace(/<[ \/]*?\w+((\s+\w+(\s*=\s*(?:".*?"|'.*?'|[^'">\s]+))?)+\s*|\s*)[ \/]*>/g, '');
}