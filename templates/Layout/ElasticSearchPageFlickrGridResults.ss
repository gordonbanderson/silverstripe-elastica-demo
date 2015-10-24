<% include AggregationSideBar %>
<div class="content-container unit size3of4 lastUnit">
	<article>
		<h1>$Title</h1>
		<div class="content">$Content</div>
		$SearchForm

		<% if $SearchPerformed %>
		<% require css("elastica/css/elastica.css") %>
		<% require css("searchdemo/css/flickrgrid.css") %>

	<div class="searchResults">

	<% if $SearchResults.Count > 0 %>
	<div class="resultsFound">
	Page $SearchResults.CurrentPage of $SearchResults.TotalPages &nbsp;($SearchResults.Count <% _t('SearchPage.RESULTS_FOUND', ' results found') %> in $ElapsedTime seconds)
	</div>
	<ul class="flickrGrid">
	<% loop $SearchResults %>
	<li>
	<img src="$SmallURL" title="$Title"/>
		<span class="caption full-caption">
			<a href="$Top.Link/similar/$ClassName/$ID" class="similarLink">Similar</a>
        	<h6><a href="$Link" target="_search"><% if $SearchHighlightsByField.Title %><% loop $SearchHighlightsByField.Title %>$Snippet<% end_loop %><% else %>$Title<% end_if %></a></h6>
			<% loop $SearchHighlights %>$Snippet &hellip;<% end_loop %>
	</span>
	</li>
	<% end_loop %>
	</ul>

	<% else %>

	<div class="noResultsFound">
	  <% _t('SearchPage.NO_RESULTS_FOUND', 'Sorry, your search query did not return any results') %>
	  <% end_if %>
	</div>

	<% if $SearchResults.MoreThanOnePage %>
	<div id="PageNumbers">
	    <div class="pagination">
	        <% if $SearchResults.NotFirstPage %>
	        <a class="prev" href="$SearchResults.PrevLink" title="View the previous page">&larr;</a>
	        <% end_if %>
	        <span>
	            <% loop $SearchResults.PaginationSummary(4) %>
	                <% if $CurrentBool %>
	                $PageNum
	                <% else %>
	                <a href="$Link" title="View page number $PageNum" class="go-to-page">$PageNum</a>
	                <% end_if %>
	            <% end_loop %>
	        </span>
	        <% if $SearchResults.NotLastPage %>
	        <a class="next" href="$SearchResults.NextLink" title="View the next page">&rarr;</a>
	        <% end_if %>
	    </div>
	</div>
	<% end_if %>
	</div>
	</div>
	</div>
	<% end_if %>
	</article>
		$CommentsForm
</div>



