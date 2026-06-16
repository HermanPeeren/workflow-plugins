# Workflow Notificationext plugin

A workflow plugin to also send notifications to the original author. It is an extended version of the core Workflow Notification plugin. 

* A copy of the core Workflow Notification plugin was made.
* Language files were put in the installable plugin
* Namespace and name was altered.
* Form was expanded with a Yes/No field to notify the author of the article.
* In Notificationext.php the onWorkflowAfterTransition() method was changed to also include the original author, if applicable.