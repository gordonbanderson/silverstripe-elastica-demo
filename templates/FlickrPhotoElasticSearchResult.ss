<div class="searchResult" id="$ClassName $ID">
<div class="infoAndImage">
<div class="searchResultInfo">
<h4><a href="$Link" target="_search"><% if $SearchHighlightsByField.Title %><% loop $SearchHighlightsByField.Title %>$Snippet<% end_loop %><% else %>$Title<% end_if %></a></h4>
<% loop $SearchHighlights %>$Snippet &hellip;<% end_loop %>
</div>
<img src="$ThumbnailURL" title="$Title"/>
</div>
<div class="searchFooter">
<% if $SearchHighlightsByField.Link %>
<% loop $SearchHighlightsByField.Link %>$Snippet<% end_loop %>
<% else %>
  $AbsoluteLink
<% end_if %>
- $TakenAt.Format(d/m/Y)
</div>
</div>

