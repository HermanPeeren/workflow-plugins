# Workflow Revision plugin

A workflow plugin to make a draft copy of an already published article, in order to edit the article in a RevisionDraft stage. It can be submitted to a RevisionReview Stage, and after approval will be copied back to the published article. The versioning of the draft wil also be copied back to the original article's versioning stack, after which the draft article is deleted.

This plugin uses the Workflow Category plugin and supposes it is installed. A next step could be to have it functioning independently of the other plugin, by detecting if the Workflow Category plugin is installed and enabled, and if not, to limit the switch of category in this plugin.

Todo:

* The plugin that handles the transition makes a copy of the article in the RevisionDraft category (not publicly available) with a custom field to keep a reference to the original article.
that new article is edited, using versioning, saving it, etc. It is just a new com_content article. It is in the RevisionDraft stage of the workflow.
* In the RevisionDraft stage there also is a "Submit for Approval" button, but now the article is sent to the RevisionReview stage. There you have an approval button, which triggers the workflow plugin to copy the edited and approved article back over the original (creating a new version).
* That temporary draft article can be (automatically) trashed after it is copied back on the real article. The versioning stack of the temporary draft copy can be copied on top of the original article stack. All in one go (copy back, copy the versioning stack of the draft and trashing the temporary article). BTW the versioning stack of the temporary draft is in the same versioning table, so it is only a matter of changing some id-fields.

After installing the plugin, you need to adjust the template override of the list of published articles of an author. In the current situation, an author is not allowed to edit their own articles, once they are published. So you’ll have to add a button to those articles to trigger the transition of a copy of an article to the RevisionDraft stage.  That button triggers a transition to the UpdateDraft stage.

