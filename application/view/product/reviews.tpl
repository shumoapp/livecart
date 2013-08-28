{includeJs file="library/lightbox/lightbox.js"}
{includeCss file="library/lightbox/lightbox.css"}

{% set metaDescription = $product.shortDescription_lang %}
{% set metaKeywords = $product.keywords_lang %}
{pageTitle}[[product.name_lang]]{/pageTitle}

<div class="reviewIndex productCategory_[[product.Category.ID]] product_[[product.ID]]">

{include file="product/layout.tpl"}

{include file="block/content-start.tpl"}

	<div class="returnToCategory">
		<a href="{productUrl product=$product}" class="returnToCategory">[[product.name_lang]]</a>
	</div>

	<h1>{maketext text="_reviews_for" params=$product.name_lang}</h1>

	<div class="resultStats">
		{include file="product/ratingSummary.tpl"}
		<div class="pagingInfo">
			{maketext text=_showing_reviews params="`$offsetStart`,`$offsetEnd`,`$product.reviewCount`"}
		</div>
		<div class="clear"></div>
	</div>

	<div class="clear"></div>

	{include file="product/reviewList.tpl"}

	{if $product.reviewCount > $perPage}
		{paginate current=$page count=$product.reviewCount perPage=$perPage url=$url}
	{/if}

	{include file="product/ratingForm.tpl"}

{include file="block/content-stop.tpl"}
{include file="layout/frontend/footer.tpl"}

</div>
