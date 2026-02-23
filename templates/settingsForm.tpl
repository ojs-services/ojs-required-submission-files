{**
 * plugins/generic/requiredFiles/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJS Services
 *
 * Settings form for the Required Submission Files plugin.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		$('#requiredFilesSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="requiredFilesSettingsForm" method="post"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="requiredFilesSettingsFormNotification"}

	{fbvFormArea id="requiredFilesSettingsFormArea"}
		{fbvFormSection title="plugins.generic.requiredFiles.settings.description" list="true"}
			{foreach from=$genres item=genre}
				{fbvElement type="checkbox" id="requiredGenres[]" value=$genre.id checked=in_array($genre.id, $requiredGenres) label=$genre.name translate=false}
			{/foreach}
		{/fbvFormSection}
		{fbvFormSection}
			<p style="margin:0;font-size:12px;color:#666;line-height:1.5;">
				<em>{translate key="plugins.generic.requiredFiles.settings.note"}</em>
			</p>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
