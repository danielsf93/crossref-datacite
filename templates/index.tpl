{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle|escape}
	</h1>
    <script type="text/javascript">
        // Attach the JS file tab handler.
        $(function () {ldelim}
            $('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
            $('#importExportTabs').tabs('option', 'cache', true);
            {rdelim});
    </script>
    <div id="importExportTabs" class="pkp_controllers_tab">
        <ul>
            <li><a href="#queue-tab">{translate key="plugins.importexport.crossref.queued"}</a></li>
            <li><a href="#deposited-tab">{translate key="plugins.importexport.crossref.deposited"}</a></li>
            <li><a href="#settings-tab">{translate key="plugins.importexport.crossref.settings"}</a></li>
        </ul>
        <div id="settings-tab">
            <script type="text/javascript">
                $(function () {ldelim}
                    $('#crossrefSettingsForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                    {rdelim});
            </script>
            <form class="pkp_form" id="crossrefSettingsForm" method="post"
                action="{plugin_url path="settings" verb="save"}">
                {if $doiPluginSettingsLinkAction}
                    {fbvFormArea id="doiPluginSettingsLink"}
                    {fbvFormSection}
                        {include file="linkAction/linkAction.tpl" action=$doiPluginSettingsLinkAction}
                    {/fbvFormSection}
                    {/fbvFormArea}
                {/if}
                {fbvFormArea id="crossrefSettingsFormArea"}
                    <p class="pkp_help">{translate key="plugins.importexport.crossref.settings.description"}</p>
                    <p class="pkp_help">{translate key="plugins.importexport.crossref.intro"}</p>
                {fbvFormSection}
                {fbvElement type="text" id="username" value=$username label="plugins.importexport.crossref.settings.form.username" maxlength="50" size=$fbvStyles.size.MEDIUM}
                {fbvElement type="text" password="true" id="password" value=$password label="plugins.importexport.crossref.settings.form.password" maxLength="50" size=$fbvStyles.size.MEDIUM}
                    <span class="instruct">{translate key="plugins.importexport.crossref.settings.form.password.description"}</span>
                    <br/>
                {/fbvFormSection}
                {fbvFormSection list="true"}
                {fbvElement type="checkbox" id="testMode" label="plugins.importexport.crossref.settings.form.testMode.description" checked=$testMode|compare:true}
                {/fbvFormSection}
                {/fbvFormArea}
                {fbvFormButtons submitText="common.save"}
            </form>
        </div>
        <div id="queue-tab">
            <script type="text/javascript">
                $(function () {ldelim}
                    $('#queueXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                    {rdelim});
            </script>
            <div class="listing" width="100%">
                <form id="queueXmlForm" class="pkp_form" action="{plugin_url path="export"}" method="post">
                    {csrf}
                    <div class="pkp_content_panel pkp_controllers_grid">
                        <div class="pkpListPanel pkpListPanel--submissions">
                            <div class="pkpListPanel__body -pkpClearfix pkpListPanel__body--submissions">
                                <div class="pkpListPanel__content pkpListPanel__content--submissions">
                                    <div class="header">
                                        <h4>
                                            {translate key="plugins.importexport.crossref.monographsOrChapter"}
                                        </h4>
                                    </div>
                                    <table aria-live="polite" class="pkpListPanel__items">
                                        <colgroup>
                                            <col class="grid-column column-select" style="width: 5%;">
                                            <col class="grid-column column-select" style="width: 15%;">
                                            <col class="grid-column column-select" style="width: 80%;">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th scope="col" style="text-align: left;">ID</th>
                                                <th scope="col" style="text-align: left;">Author</th>
                                                <th scope="col" style="text-align: left;">Content</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        {foreach $itemsQueue as $key=>$item}
                                            <tr class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--hasSummary">
                                                <td>
                                                    <div class="pkpListPanelItem--submission__id">
                                                        {$item["id"]}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="pkpListPanelItem--submission__author">
                                                        {$item["authors"]}
                                                    </div>
                                                </td>
                                                <td><hr>
                                                    <div class="pkpListPanelItem__summary -pkpClearfix">
                                                        <div class="pkpListPanelItem--submission__item">
                                                            <div class="pkpListPanelItem--submission__reviewerWorkflowLink"><span
                                                                        class="-screenReader">ID</span>
                                                            </div>
                                                            <div class="pkpListPanelItem--submission__title">
                                                                {$item["title"]}<br />
                                                                DOI: {$item["pubId"]}<br />
                                                                {if $item["chapterPubIds"]}
                                                                    {translate key="plugins.importexport.crossref.chapterDoiCount"}:  {$item["chapterPubIds"]|count}
                                                                {/if}
                                                            </div>
                                                            <div class="pkpListPanelItem--submission__activity">
                                                                {if $item["notices"]}
                                                                    {foreach from=$item["notices"] item=$notice}
                                                                        <span aria-hidden="true" class="fa fa-exclamation-triangle pkpIcon--inline"></span> {$notice} <br />
                                                                    {/foreach}
                                                                {/if}
                                                                {if $item["errors"]}
                                                                    {foreach from=$item["errors"] item=$error}
                                                                        <span aria-hidden="true" class="fa fa-exclamation-triangle pkpIcon--inline"></span> {$error} <br />
                                                                    {/foreach}
                                                                {/if}
                                                            </div>
                                                        </div>
                                                        <div class="pkpListPanelItem--submission__stage">
                                                            <div class="pkpListPanelItem--submission__stageRow">
                                                                {if !$item["errors"]}                                                        
                                                                <button class="pkpBadge pkpBadge--button pkpBadge--dot">
                                                                    <a href="{$plugin}/export?submission={$item["id"]}" class="">
                                                                        {translate key="plugins.importexport.crossref.export"}
                                                                    </a>
                                                                </button> 
                                                                <button class="pkpBadge pkpBadge--button pkpBadge--dot">
                                                                    <a href="{$plugin}/deposit?submission={$item["id"]}" class="">
                                                                        {translate key="plugins.importexport.crossref.deposit"}
                                                                    </a>
                                                                </button>
                                                                {/if} 
                                                                <div aria-hidden="true"
                                                                    class="pkpListPanelItem--submission__flags">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        {/foreach}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="pkpListPanel__footer -pkpClearfix">
                                <div class="pkpListPanel__count">
                                    {$itemsSizeQueue} submissions
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
        <div id="deposited-tab">
            <script type="text/javascript">
                $(function () {ldelim}
                    $('#depositedXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                    {rdelim});
            </script>
            <div class="listing" width="100%">
                <form id="depositedXmlForm" class="pkp_form" action="{plugin_url path="export"}" method="post">
                    {csrf}
                    <div class="pkp_content_panel pkp_controllers_grid">
                        <div class="pkpListPanel pkpListPanel--submissions">
                            <div class="pkpListPanel__body -pkpClearfix pkpListPanel__body--submissions">
                                <div class="pkpListPanel__content pkpListPanel__content--submissions">
                                    <div class="header">
                                        <h4>
                                            {translate key="plugins.importexport.crossref.deposited"}
                                        </h4>
                                    </div>
                                    <table aria-live="polite" class="pkpListPanel__items">
                                        <colgroup>
                                            <col class="grid-column column-select" style="width: 5%;">
                                            <col class="grid-column column-select" style="width: 15%;">
                                            <col class="grid-column column-select" style="width: 80%;">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th scope="col" style="text-align: left;">ID</th>
                                                <th scope="col" style="text-align: left;">Author</th>
                                                <th scope="col" style="text-align: left;">Content</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        {foreach $itemsDeposited as $key=>$item}
                                            <tr class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--hasSummary">
                                                <td>
                                                    <div class="pkpListPanelItem--submission__id">
                                                        {$item["id"]}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="pkpListPanelItem--submission__author">
                                                        {$item["authors"]}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="pkpListPanelItem__summary -pkpClearfix">
                                                        <div class="pkpListPanelItem--submission__item">
                                                            <div class="pkpListPanelItem--submission__reviewerWorkflowLink">
                                                                <span class="-screenReader">ID</span>
                                                            </div>
                                                            <div class="pkpListPanelItem--submission__title">
                                                                {$item["title"]}<br />
                                                                DOI: {$item["pubId"]}<br />
                                                                {if $item["chapterPubIds"]}
                                                                    {translate key="plugins.importexport.crossref.chapterDoiCount"}:  {$item["chapterPubIds"]|count}
                                                                {/if}
                                                            </div>
                                                            <div class="pkpListPanelItem--submission__activity">
                                                                {if $item["notices"]}   
                                                                    {foreach from=$item["notices"] item=$notice}
                                                                        <span aria-hidden="true" class="fa fa-exclamation-triangle pkpIcon--inline"></span> {$notice} <br />
                                                                    {/foreach}
                                                                {/if}
                                                                {if $item["errors"]}   
                                                                    {foreach from=$item["errors"] item=$error}
                                                                        <span aria-hidden="true" class="fa fa-exclamation-triangle pkpIcon--inline"></span> {$error} <br />
                                                                    {/foreach}
                                                                {/if}
                                                            </div>
                                                        </div>
                                                        <div class="pkpListPanelItem--submission__stage">
                                                            <div class="pkpListPanelItem--submission__stageRow">
                                                                {if !$item["errors"]}                                                  
                                                                <button class="pkpBadge pkpBadge--button pkpBadge--dot">
                                                                    <a href="{$plugin}/export?submission={$item["id"]}" class="">
                                                                        {translate key="plugins.importexport.crossref.export"}
                                                                    </a>
                                                                </button> 
                                                                <button class="pkpBadge pkpBadge--button pkpBadge--dot">
                                                                    <a href="{$plugin}/deposit?submission={$item["id"]}" class="">
                                                                        {translate key="plugins.importexport.crossref.redeposit"}
                                                                    </a>                                                           
                                                                </button>
                                                                {/if} 
                                                                <div aria-hidden="true" class="pkpListPanelItem--submission__flags">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        {/foreach}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="pkpListPanel__footer -pkpClearfix">
                                <div class="pkpListPanel__count">
                                    {$itemsSizeDeposited} submissions
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
{/block}