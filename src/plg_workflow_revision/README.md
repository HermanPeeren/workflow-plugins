# Workflow Revision plugin

A workflow plugin to make a draft copy of an already published article, in order to edit the article in a Revision Draft stage. From there it can be submitted to a Revision Review Stage, and after approval will be copied back to the published article. The versioning of the draft wil also be copied back to the original article's versioning stack, after which the draft article is deleted.

This Workflow Revision plugin uses the same principle as the Workflow Category plugin: work on a draft in a category that is not publicly accessible. I made this plugin for “items” of any component that implements the workflow. 

The plugin doesn't hide the manual setting of the categories for revision draft and revision review. I use this plugin in combination with the Workflow Category plugin, which already takes care of that.

* After the transition the plugin makes a copy of the article, and places it in the RevisionDraft category (not publicly accessible).
* I added a table to keep track of the articles in revision, and their original article that is still published.
* That new article is edited, using versioning, saving it, etc. It is just a new com_content article. It is in the RevisionDraft stage of the workflow.
* In the RevisionDraft stage there also is a "Submit for Approval" button, but now the article is sent to the RevisionReview stage. There you have an approval button, which triggers the workflow plugin to copy the edited and approved article back over the original.
* The versioning stack of the temporary draft copy is copied on top of the original article stack.
* The temporary draft article is deleted after it is copied back to the original article.  All in one go (copy back, copy the versioning stack of the draft, and delete the temporary article).

After installing the plugin, you need to create categories for the revision draft and revision review, and set them in the plugin parameters. To make it easily workable in the frontend, you should create a template override of the list of published articles of an author. In the current situation, an author is not allowed to edit their own articles, once they are published. So you’ll have to add a button to those articles to trigger the transition of a copy of an article to the RevisionDraft stage.

