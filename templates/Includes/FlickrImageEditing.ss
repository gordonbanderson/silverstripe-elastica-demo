<div class="field">
<img class="centered" src="$FlickrPhoto.LargeURL" alt="$Title.XML" title="$Title.XML" style="margin-left:auto; margin-right: auto;"/>
</div>
<% if $FlickrSetID %>
<div class="field">

<button data-flickr-photo-id="{$FlickrPhoto.ID}" data-flickr-set-id="$FlickrSetID"
class="action ss-ui-action-constructive ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary"
id="changeMainPictureButton" data-icon="accept" role="button" aria-disabled="false"><span class="ui-button-icon-primary ui-icon"></span><span class="ui-button-text">
		Make this the main image
	</span></button>
</div>

<% end_if %>
