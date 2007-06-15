Backend.OrderedItem = {
    activeListCallbacks: 
    {
        beforeDelete: function(li){ 
            if(confirm(Backend.OrderedItem.messages.areYouSureYouWantToDelete)) 
            {
                return Backend.OrderedItem.Links.remove + "/?ShippingID=" + this.getRecordId(li);
            }
        },
        afterDelete: function(li, response){
            if(!response.error) {
                this.remove(li);
                var orderID = this.getRecordId(li, 3);
                Backend.OrderedItem.updateReport($("orderShipment_report_" + orderID));
            }
        },
        beforeSort: function(li, order){ 
            var oldShipmentId = this.getRecordId(li, 2);
            var newShipmentId = this.getRecordId(this.ul, 1)
            
            if(oldShipmentId != newShipmentId)
            {
                return Backend.OrderedItem.Links.changeShipment + "/" + this.getRecordId(li) + "?from=" + oldShipmentId + "&to=" + newShipmentId
            }
        },
        afterSort: function(li, response){
            var response = eval("(" + response + ")");
            var orderID = this.getRecordId(li, 2);
            
            var shipmentsActiveList = ActiveList.prototype.getInstance('orderShipments_list_' + orderID);
            
            var oldShipmentLi = $("orderShipments_list_" + orderID + "_" + response.oldShipment.ID);
            var newShipmentLi = $("orderShipments_list_" + orderID + "_" + response.newShipment.ID)

            if('success' == response.status)
            {
                try
                {
                    // Old shipment changes
                    var oldSubtotal = oldShipmentLi.down('.orderShipment_info_subtotal');
                        oldSubtotal.down('.pricePrefix').innerHTML = response.oldShipment.prefix;
                        oldSubtotal.down('.price').innerHTML = parseFloat(response.oldShipment.amount);
                        oldSubtotal.down('.priceSuffix').innerHTML = response.oldShipment.suffix;
                    console.info(newShipmentLi.down('.orderShipment_info_total').down('.price'));
                    
                    var oldShippingAmount = oldShipmentLi.down('.orderShipment_info_shippingAmount');
                        oldShippingAmount.down('.pricePrefix').innerHTML = response.oldShipment.prefix;
                        oldShippingAmount.down('.price').innerHTML = parseFloat(response.oldShipment.shippingAmount);
                        oldShippingAmount.down('.priceSuffix').innerHTML = response.oldShipment.suffix;
                    console.info(newShipmentLi.down('.orderShipment_info_total').down('.price'));
                    
                    var oldTotalAmount = oldShipmentLi.down('.orderShipment_info_total');
                        oldTotalAmount.down('.pricePrefix').innerHTML = response.oldShipment.prefix;
                        oldTotalAmount.down('.price').innerHTML = parseFloat(response.oldShipment.totalAmount);
                        oldTotalAmount.down('.priceSuffix').innerHTML = response.oldShipment.suffix;
                    console.info(newShipmentLi.down('.orderShipment_info_total').down('.price'));
                                    
                    // New shipment changes
                    var newSubtotal = newShipmentLi.down('.orderShipment_info_subtotal');
                        newSubtotal.down('.pricePrefix').innerHTML = response.newShipment.prefix;
                        newSubtotal.down('.price').innerHTML = parseFloat(response.newShipment.amount);
                        newSubtotal.down('.priceSuffix').innerHTML = response.newShipment.suffix;
                    console.info(newShipmentLi.down('.orderShipment_info_total').down('.price'));
                    
                    var newShippingAmount = newShipmentLi.down('.orderShipment_info_shippingAmount');
                        newShippingAmount.down('.pricePrefix').innerHTML = response.newShipment.prefix;
                        newShippingAmount.down('.price').innerHTML = parseFloat(response.newShipment.shippingAmount);
                        newShippingAmount.down('.priceSuffix').innerHTML = response.newShipment.suffix;
                        
                    console.info(newShipmentLi.down('.orderShipment_info_total').down('.price'));
                    var newTotalAmount = newShipmentLi.down('.orderShipment_info_total');
                        newTotalAmount.down('.pricePrefix').innerHTML = response.newShipment.prefix;
                        newTotalAmount.down('.price').innerHTML = parseFloat(response.newShipment.totalAmount);
                        newTotalAmount.down('.priceSuffix').innerHTML = response.newShipment.suffix;
    
                    shipmentsActiveList.highlight(newShipmentLi);
                
                    Backend.OrderedItem.updateReport($("orderShipment_report_" + orderID));
                }
                catch(e)
                {
                    console.info(e)
                }
            }
            else
            {
                li.id = 'orderShipmentsItems_list_' + orderID + '_' + response.oldShipment.ID + '_' + this.getRecordId(li); 
                oldShipmentLi.down('ul').appendChild(li);
                shipmentsActiveList.highlight(oldShipmentLi, 'red');
                
			    new Backend.SaveConfirmationMessage($('noRateInShippingServiceIsAvailableError'))
            }
        }
    },
    
    updateReport: function(report)
    {
        var reportValues = { 'subtotalAmount': 0, 'shippingAmount': 0, 'totalAmount': 0 };
        document.getElementsByClassName('orderShipment_info', report.up('.tabPageContainer')).each(function(shipmentReport) 
        {
            reportValues['subtotalAmount'] += (parseFloat(shipmentReport.down('.orderShipment_info_subtotal').down('.price').innerHTML) || 0);
            reportValues['shippingAmount'] += (parseFloat(shipmentReport.down('.orderShipment_info_shippingAmount').down('.price').innerHTML) || 0);
            reportValues['totalAmount'] += (parseFloat(shipmentReport.down('.orderShipment_info_total').down('.price').innerHTML) || 0);
        });
        
        report.down('.orderShipment_report_subtotal').down('.price').innerHTML = Math.round(reportValues['subtotalAmount'] * 100) / 100;;
        report.down('.orderShipment_report_shippingAmount').down('.price').innerHTML = Math.round(reportValues['shippingAmount'] * 100) / 100;;
        report.down('.orderShipment_report_total').down('.price').innerHTML = Math.round(reportValues['totalAmount'] * 100) / 100;;
   }
};


Backend.Shipment = Class.create();
Backend.Shipment.prototype = 
{
    instances: {},
    
    initialize: function(root)
    {
        try
        {
            this.findUsedNodes(root);
            this.bindEvents();
            this.shipmentsActiveList = ActiveList.prototype.getInstance(this.nodes.shipmentsList);
            
            if(this.nodes.form.elements.namedItem('ID').value)
            {
                Form.State.backup(this.nodes.form);
            }
        }
        catch(e)
        {
            console.info(e);
        }
    },
        
    getInstance: function(rootNode)
    {
        var rootId = $(rootNode).id;
        if(!Backend.Shipment.prototype.instances[rootId])
        {
            Backend.Shipment.prototype.instances[rootId] = new Backend.Shipment(rootId);
        }
        
        return Backend.Shipment.prototype.instances[rootId];
    },
    
    findUsedNodes: function(root)
    {
        this.nodes = {};
        
        this.nodes.root = $(root);
        this.nodes.form = this.nodes.root;

        this.nodes.controls = this.nodes.root.down('.controls');
        this.nodes.save = this.nodes.controls.down('.submit');
        this.nodes.cancel = this.nodes.controls.down('.cancel');
        
        this.nodes.shipmentsList = $('orderShipments_list_' + this.nodes.form.elements.namedItem('orderID').value);

        if(!this.nodes.form.elements.namedItem('ID').value)
        {
            this.nodes.menu = $('orderShipments_menu_' + this.nodes.form.elements.namedItem('orderID').value);
            this.nodes.menuCancelLink = $('orderShipments_new_' + this.nodes.form.elements.namedItem('orderID').value + "_cancel");
            this.nodes.menuShowLink = $('orderShipments_new_' + this.nodes.form.elements.namedItem('orderID').value + "_show");
            this.nodes.menuForm = $('orderShipments_new_' + this.nodes.form.elements.namedItem('orderID').value + "_form");
        }
    },
    
    bindEvents: function()
    {
       var self = this;
       Event.observe(this.nodes.save, 'click', function(e) { Event.stop(e); self.save(); });
       Event.observe(this.nodes.cancel, 'click', function(e) { Event.stop(e); self.cancel(); });
       if(!this.nodes.form.elements.namedItem('ID').value)
       {
           Event.observe(this.nodes.menuCancelLink, 'click', function(e) { Event.stop(e); self.cancel(); });
       }
    },
    
    showNewForm: function()
    {
        ActiveForm.prototype.hideMenuItems(this.nodes.menu, [this.nodes.menuCancelLink]);
        ActiveForm.prototype.showNewItemForm(this.nodes.menuShowLink, this.nodes.menuForm); 
        ActiveList.prototype.collapseAll();
    },
    
    hideNewForm: function()
    {
        ActiveForm.prototype.hideMenuItems(this.nodes.menu, [this.nodes.menuShowLink]);
        ActiveForm.prototype.hideNewItemForm(this.nodes.menuCancelLink, this.nodes.menuForm); 
    },
    
    save: function()
    {
        var self = this;
        
        ActiveForm.prototype.resetErrorMessages(this.nodes.form);
        var action = this.nodes.form.elements.namedItem('ID').value
            ? Backend.Shipment.Links.update
            : Backend.Shipment.Links.create;
            
        new Ajax.Request(action, {
            method: 'post',
            parameters: Form.serialize(this.nodes.form),
            onSuccess: function(response) { 
                var response = eval("(" + response.responseText + ")");
                self.afterSave(response);     
            }
        });

        console.info('save');
    },
    
    afterSave: function(response)
    {
        if(response.status == 'success')
        {
            ActiveForm.prototype.resetErrorMessages(this.nodes.form);
            if(!this.nodes.form.elements.namedItem('ID').value)
            {
                var title = '<h3 class="orderShipment_title">' + this.nodes.form.elements.namedItem('shippingServiceID').options[this.nodes.form.elements.namedItem('shippingServiceID').selectedIndex].text + '</h3>';
                var stats = $("orderShipment_" + this.nodes.form.elements.namedItem('orderID').value + "_info_empty").innerHTML;
                var ul = '<ul id="orderShipmentsItems_list_' + this.nodes.form.elements.namedItem('orderID').value + '_' +response.shipment.ID + '" class="activeList_add_sort activeList_add_delete orderShipmentsItem activeList_accept_orderShipmentsItem"></ul>'
                
                var li = this.shipmentsActiveList.addRecord(response.shipment.ID, title + stats + ul);

                ActiveList.prototype.getInstance($('orderShipmentsItems_list_' + this.nodes.form.elements.namedItem('orderID').value + '_' + response.shipment.ID), Backend.OrderedItem.activeListCallbacks);
                Element.addClassName(li, this.prefix  + 'item');
                                
                ActiveList.prototype.recreateVisibleLists();

                var newSubtotal = li.down('.orderShipment_info_subtotal');
                    newSubtotal.down('.pricePrefix').innerHTML = response.shipment.prefix;
                    newSubtotal.down('.price').innerHTML = response.shipment.amount;
                    newSubtotal.down('.priceSuffix').innerHTML = response.shipment.suffix;
                
                var newShippingAmount = li.down('.orderShipment_info_shippingAmount');
                    newShippingAmount.down('.pricePrefix').innerHTML = response.shipment.prefix;
                    newShippingAmount.down('.price').innerHTML = response.shipment.shippingAmount;
                    newShippingAmount.down('.priceSuffix').innerHTML = response.shipment.suffix;
                
                var newTotalAmount = li.down('.orderShipment_info_total');
                    newTotalAmount.down('.pricePrefix').innerHTML = response.shipment.prefix;
                    newTotalAmount.down('.price').innerHTML = response.shipment.totalAmount;
                    newTotalAmount.down('.priceSuffix').innerHTML = response.shipment.suffix;
                
                Element.addClassName(li, 'orderShipment');
                
                Backend.OrderedItem.updateReport($("orderShipment_report_" + this.nodes.form.elements.namedItem('orderID').value));

                this.shipmentsActiveList.highlight(li);
                this.hideNewForm();
            }
            else
            {
                Form.State.backup(this.nodes.form);
                this.servicesActiveList.toggleContainer(this.nodes.root.up('li'), 'edit');
            }
        }
        else
        {
            ActiveForm.prototype.setErrorMessages(this.nodes.form, response.errors);
        }
    },
    
    cancel: function()
    {
        if(!this.nodes.form.elements.namedItem('ID').value)
        {
            this.hideNewForm();
        }
        else
        {
            this.servicesActiveList.toggleContainerOff(this.nodes.root.up('.activeList_editContainer'));
            Form.State.restore(this.nodes.form);
        }
    }
}



Backend.Shipment.Callbacks =
{
    beforeDelete: function(li) { 
        if(confirm(Backend.Shipment.Messages.areYouSureYouWantToDelete)) 
        {
            return Backend.Shipment.Links.remove + "/" + this.getRecordId(li);
        }
    },
    afterDelete: function(li, response) {
        if(!response.error) {
            this.remove(li);
            var orderID = this.getRecordId(li, 2);
            Backend.OrderedItem.updateReport($("orderShipment_report_" + orderID));
        }
    },
    
    beforeEdit:     function(li) 
    {
        if(!Backend.Shipment.Controller.prototype.getInstance(li.down('.productRelationshipGroup_form')))
        {
            return Backend.Shipment.Links.edit + "/" + this.getRecordId(li);
        }
        else
        {
            with(Backend.Shipment.Controller.prototype.getInstance(li.down('.productRelationshipGroup_form')))
            {
                if('block' != view.nodes.root.style.display) showForm();
                else hideForm();
            }
        }
    },
    afterEdit:      function(li, response) 
    { 
        try
        {
            response = eval("(" + response + ")");
        }
        catch(e)
        {
            console.info(e);
        }
    }
}