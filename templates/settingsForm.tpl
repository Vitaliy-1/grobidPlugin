{**
 * plugins/generic/JATSParserPlugin/settingsForm.tpl
 *
 * Copyright (c) 2017-2018 Vitalii Bezsheiko
 * Distributed under the GNU GPL v3.
 *
 * JATSParserPlugin plugin settings
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#grobidSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		{rdelim});
</script>

<form class="pkp_form" id="grobidSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="grobidSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.grobid.settings.description"}</div>

	{fbvFormArea id="grobidSettingsFormArea"}
		{fbvFormSection list=true}
			{fbvElement type="text" id="host" name="host" value=$host required="true" label="plugins.generic.jatsParser.settings.grobidUrl"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
