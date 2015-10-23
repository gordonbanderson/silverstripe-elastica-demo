<div class="searchResult" id="$ClassName $ID">
<div class="searchResultInfo">
<h4><a href="$Link" target="_search"><% if $SearchHighlightsByField.Title_standard %><% loop $SearchHighlightsByField.Title_standard %>$Snippet<% end_loop %><% else %>$Title<% end_if %></a></h4>
<% loop $SearchHighlights %>$Snippet &hellip;<% end_loop %>
</div>
<div class="searchFooter">
<% if $SearchHighlightsByField.Link_standard %>
<% loop $SearchHighlightsByField.Link_standard %>$Snippet<% end_loop %>
<% else %>
  $AbsoluteLink
<% end_if %>
- <a href="$SimilarSearchLink">Similar</a>
- $PublishDate.Format(d/m/Y)
&nbsp; <span class="source">(Source: $Source)</span>
</div>
</div>

