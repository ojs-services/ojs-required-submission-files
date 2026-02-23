{**
 * plugins/generic/requiredFiles/templates/requiredFilesPanel.tpl
 *
 * Copyright (c) 2026 OJS Services
 *
 * Required files upload panel injected into submission Step 2.
 *}

{if $rfValidationErrorStr}
<div id="rf-validation-error" class="rf-validation-error">
	<div class="rf-error-icon">!</div>
	<div class="rf-error-content">
		<strong>{translate key="plugins.generic.requiredFiles.validation.errorTitle"}</strong>
		<p>{$rfValidationErrorStr|escape}</p>
	</div>
</div>
{/if}

<div id="rf-info-banner" class="rf-info-banner">
	<div class="rf-info-icon">&#9432;</div>
	<div class="rf-info-content">
		<strong>{translate key="plugins.generic.requiredFiles.banner.title"}</strong>
		<span>
		{foreach from=$rfRequiredGenres item=genre name=genreLoop}
			{$genre.name|escape}{if !$smarty.foreach.genreLoop.last}, {/if}
		{/foreach}
		</span>
	</div>
</div>

<div id="rf-required-section"
	class="rf-required-section"
	data-api-url="{$rfApiUrl|escape}"
	data-submission-id="{$rfSubmissionId|escape}"
	data-required-genres='{$rfRequiredGenresJson|escape:'htmlall'}'
	data-existing-files='{$rfExistingFilesJson|escape:'htmlall'}'
	data-label-uploaded="{translate key="plugins.generic.requiredFiles.status.uploaded"}"
	data-label-not-uploaded="{translate key="plugins.generic.requiredFiles.status.notUploaded"}"
	data-label-replace="{translate key="plugins.generic.requiredFiles.action.replace"}"
	data-label-upload-error="{translate key="plugins.generic.requiredFiles.validation.uploadError"}">

	<h4 class="rf-section-title">{translate key="plugins.generic.requiredFiles.section.required"}</h4>

	{foreach from=$rfRequiredGenres item=genre}
	<div class="rf-genre-slot" data-genre-id="{$genre.id|escape}" data-genre-name="{$genre.name|escape}">
		<div class="rf-genre-label">
			<span class="rf-genre-name">{$genre.name|escape}</span>
			<span class="rf-genre-status rf-status-missing">{translate key="plugins.generic.requiredFiles.status.notUploaded"}</span>
		</div>
		<div class="rf-genre-upload">
			<label class="rf-file-input pkp_button">
				{translate key="plugins.generic.requiredFiles.action.chooseFile"}
				<input type="file" class="rf-file-field" style="display:none;" />
			</label>
			<span class="rf-file-name"></span>
		</div>
		<div class="rf-genre-progress" style="display:none;">
			<div class="rf-progress-bar"><div class="rf-progress-fill"></div></div>
			<span class="rf-progress-text">0%</span>
		</div>
		<div class="rf-genre-uploaded" style="display:none;"></div>
		<div class="rf-genre-error" style="display:none;"></div>
	</div>
	{/foreach}
</div>

<div class="rf-optional-separator">
	<h4 class="rf-section-title rf-optional-title">{translate key="plugins.generic.requiredFiles.section.optional"}</h4>
</div>
