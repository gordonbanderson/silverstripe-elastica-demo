<div class="searchResult" id="$ClassName $ID">
<div class="searchResultInfo">
<h4><a href="$Link" target="_search"><% if $SearchHighlightsByField.Title %><% loop $SearchHighlightsByField.Title %>$Snippet<% end_loop %><% else %>$Title<% end_if %></a></h4>
<% loop $SearchHighlights %>$Snippet &hellip;<% end_loop %>
</div>
<div class="searchFooter">
<% if $SearchHighlightsByField.Link %>
<% loop $SearchHighlightsByField.Link %>$Snippet<% end_loop %>
<% else %>
  $AbsoluteLink
<% end_if %>
- $PublishDate.Format(d/m/Y)
&nbsp; <span class="source">(Source: $Source)</span>
</div>
</div>

