{% extends "layout/frontend.tpl" %}

{% title %}{t _cvv}{% endblock %}
[[ partial("checkout/layout.tpl") ]]
{% block content %}

	[[ partial("checkout/cvvHelp.tpl") ]]

{% endblock %}
