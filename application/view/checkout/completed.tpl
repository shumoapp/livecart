{% extends "layout/frontend.tpl" %}

{% block title %}{t _order_completed}{{% endblock %}
{include file="layout/frontend/layout.tpl" hideLeft=true}
{% block content %}

	{if $order.isPaid}
		{t _completed_paid}
	{else}
		{t _completed_offline}

		{if $transactions.0.serializedData.handlerID}
			{include file="checkout/offlineMethodInfo.tpl" method=$transactions.0.serializedData.handlerID|@substr:-1}
		{/if}
	{/if}

	{include file="checkout/completeOverview.tpl" nochanges=true}
	{include file="checkout/orderDownloads.tpl"}

{% endblock %}
