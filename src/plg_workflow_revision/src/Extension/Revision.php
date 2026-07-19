<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Workflow.revision
 *
 * @copyright   (C) 2026 Herman Peeren, Yepr
 * @license     GNU General Public License version 3; see LICENSE.txt
 */

namespace Yepr\Plugin\Workflow\Revision\Extension;

use Joomla\CMS\Event\Model;
use Joomla\CMS\Event\Workflow\WorkflowTransitionEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Workflow\WorkflowPluginTrait;
use Joomla\CMS\Workflow\WorkflowServiceInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Workflow Revision Plugin
 *
 */
final class Revision extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
	use WorkflowPluginTrait;

    /**
     * The id of the Revision Draft category (as set in the parameters of the plugin).
     *
     * @var    integer
     */
    private $revisionDraftCategoryId = 0;


    /**
     * The id of the Revision Review category (as set in the parameters of the plugin).
     *
     * @var    integer
     */
    private $revisionReviewCategoryId = 0;


    /**
     * The id of the current user.
     *
     * @var    integer
     */
    private $currentUserId = 0;

	/**
	 * The id of the stage we are going to.
	 * @var integer
	 */
	private $toStageId;

	/**
	 * The id of the stage we came from.
	 * @var integer
	 */
	private $fromStageId;


    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     */
    protected $autoloadLanguage = true;

	/**
     * Returns an array of events this subscriber will listen to.
     *
     * @return   array
     *
     * @since   4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm'       => 'onContentPrepareForm',
            'onWorkflowBeforeTransition' => 'onWorkflowBeforeTransition',
            'onWorkflowAfterTransition'  => 'onWorkflowAfterTransition'
        ];
    }

    /**
     * Add Revision-specific action-options to the transition form.
     *
     * @param   Model\PrepareFormEvent   $event  The event
     *
     * @return   void
     */
    public function onContentPrepareForm(Model\PrepareFormEvent $event):void
    {
        $form    = $event->getForm();
        $data    = $event->getData();
        $context = $form->getName();

        // Extend the transition form
        if ($context === 'com_workflow.transition') {
            $this->enhanceWorkflowTransitionForm($form, $data);
        }
    }

    /**
     * Don't do this transition if the original item is already in revision.
     * A message is shown that the transition cannot be run.
     *
     * @param   WorkflowTransitionEvent  $event  The workflow event being processed.
     *
     * @return   void
     */
    public function onWorkflowBeforeTransition(WorkflowTransitionEvent $event):void
    {
        $context = $event->getArgument('extension');
        $transition = $event->getArgument('transition');
        $pks = $event->getArgument('pks');

        if (!$this->isSupported($context)) {
            return;
        }

        // We want some revision.
        if ($transition->options['revision_category']) {

            // For all item primary keys: check if this item is not already in revision.
            foreach ($pks as $pk) {
	            // If this item is not in the revision table, $revisionInfoPkOriginal will be null
	            $revisionInfoPkOriginal = $this->revisionInfoOriginal($context, $pk);
	            if (!is_null($revisionInfoPkOriginal)) {
		            // Item is already in revision, stop the transition and show a message why.
					$event->setStopTransition();

					// Show a warning.
		            $message = $this->getApplication()->getLanguage()->_('PLG_WORKFLOW_REVISION_MESSAGE_ALREADY_IN_REVISION');
		            $this->getApplication()->enqueueMessage($message, 'warning');
					// You could check if it is in revision by the current user or not, to provide more information.
	            }
            }
        }
    }

    /**
     * Put the copied article in the correct revision category (or copy back to original).
     *
     * @param   WorkflowTransitionEvent  $event  The workflow event being processed.
     *
     * @return   void
     */
    public function onWorkflowAfterTransition(WorkflowTransitionEvent $event):void
    {
        $context = $event->getArgument('extension');
        // should check if valid context com_extensionname.tablename and there must be a category id in the table
        $transition = $event->getArgument('transition');
        $pks = $event->getArgument('pks');

        if (!$this->isSupported($context)) {
            return;
        }

        // Process transition if we have a revision
        if ($revisionCategory = $transition->options['revision_category']) {
            // Initialisation
            // Set the id of the Revision Draft and the Revision Review category from the plugin's parameters
            $this->revisionDraftCategoryId  = $this->params->get('revision_draft_category_id');
            $this->revisionReviewCategoryId = $this->params->get('revision_review_category_id');
            // Set the current user id; this user will edit the draft
            $this->currentUserId = $this->getApplication()->getIdentity()->id;
			// Set the stage_id we are going to.
	        $this->toStageId = $transition->to_stage_id;
	        $this->fromStageId = $transition->from_stage_id;

            // For all item primary keys
            foreach ($pks as $pk) {
	            // If this item is not in the revision table, $revisionInfoPkOriginal and $revisionInfoPkCopy will be null
	            $revisionInfoPkOriginal = $this->revisionInfoOriginal($context, $pk);
	            $revisionInfoPkCopy     = $this->revisionInfoCopy($context, $pk);
	            $notInRevisionInfo = false;
	            if (is_null($revisionInfoPkOriginal) && (is_null($revisionInfoPkCopy))) {
		            $notInRevisionInfo = true;
	            }

	            // If we go to a Draft stage, we have to make a copy of the original if it doesn't exist yet
	            if (($revisionCategory === 'draft')  && $notInRevisionInfo) {
		            // Make a copy of the original article
		            $this->copyItemToDraft($context, $pk);
	            }
				else {
					// We handle a copied item, which must be in the revision table
					if (!is_null($this->revisionInfoCopy($context, $pk))) {

						// If the item is approved after review, it can be copied back over the original item
						if ($revisionCategory === 'approved')
						{
							$this->copyReviewToOriginal($context, $pk);
						}
						else
						{
							// Put in the Revision Draft or Revision Review Category
							$this->setRevisionCategory($context, $pk, $revisionCategory);
						}
					}
				}

            }
        }
    }

    /**
     * Put an article with this primary key in the Revision Draft or Revision Review Category
     *
     * @param string  $context
     * @param integer $pk
     * @param string  $revisionCategory
     *
     * @return void
     */
    private function setRevisionCategory(string $context, int $pk, string $revisionCategory):void
    {
        list($componentName, $tableName)  = explode('.', $context);
        $table = $this->getApplication()->bootComponent($componentName)->getMVCFactory()->createTable($tableName);
        $table->load($pk);
        $catColumn = $table->getColumnAlias('catid');
	    $catid=0;
	    switch($revisionCategory)
	    {
		    case 'draft':  $catid = $this->revisionDraftCategoryId; break;
		    case 'review': $catid = $this->revisionReviewCategoryId; break;
	    }
	    if ($revisionCategory && $catid > 0) {
		    $table->$catColumn = $catid;
	    }
    }

    /**
     * Get the revision info of an original item id
     *
     * @param string  $context
     * @param integer $originalId
     *
     * @return object|null
     */
    private function revisionInfoOriginal(string $context, int $originalId):object|null
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
	        ->select('*')
	        ->from($db->quoteName('#__revision_copy_original'))
	        ->where($db->quoteName('context') . ' = :context')
	        ->where($db->quoteName('original_id') . ' = :originalId')
	        ->bind(':context', $context)
	        ->bind(':originalId', $originalId, ParameterType::INTEGER);
	    $db->setQuery($query);

	    return $db->loadObject();
    }

    /**
     * Get the revision info of a copied item id
     *
     * @param string $context
     * @param integer $pk
     *
     * @return object|null
     */
    private function revisionInfoCopy($context, $copyId):object|null
    {
	    $db = $this->getDatabase();
	    $query = $db->getQuery(true)
		    ->select('*')
		    ->from($db->quoteName('#__revision_copy_original'))
		    ->where($db->quoteName('context') . ' = :context')
		    ->where($db->quoteName('copy_id') . ' = :copyId')
		    ->bind(':context', $context)
		    ->bind(':copyId', $copyId, ParameterType::INTEGER);
	    $db->setQuery($query);

	    return $db->loadObject();
    }

    /**
     * Make a copy of an item to the Revision Draft category
     * Create via a Table, and add the new item to the workflow_associations table.
     *
     * @param string  $context
     * @param integer $id
     *
     * @return void
     */
    private function copyItemToDraft($context, $originalId):void
    {
		// Restore the workflow stage of the original article
	    $db = $this->getDatabase();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__workflow_associations'))
			->set([
				$db->quoteName('stage_id') . ' = :stageId'
			])
			->where($db->quoteName('extension') . ' = :context')
			->where($db->quoteName('item_id') . ' = :originalId')
			->bind(':context', $context)
			->bind(':originalId', $originalId, ParameterType::INTEGER)
			->bind(':stageId', $this->fromStageId, ParameterType::INTEGER);

	    $db->setQuery($query);
	    $db->execute();

		// Get the original item
	    list($componentName, $tableName)  = explode('.', $context);
	    $table = $this->getApplication()->bootComponent($componentName)->getMVCFactory()->createTable($tableName);
	    $table->load($originalId);

	    $catColumn       = $table->getColumnAlias('catid');
	    $createdByColumn = $table->getColumnAlias('created_by');
	    $createdColumn   = $table->getColumnAlias('created');

	    // Save to new item (id = 0) in Revision Draft category
	    // The item should have a created_by (int) and created (datetime) field, which should be set to current user and time.
	    // Ignore a created_by_alias; will stay the same in the original table.
	    $table->id               = 0;
		$table->$catColumn       = $this->revisionDraftCategoryId;
		$table->$createdByColumn = $this->currentUserId;
		$table->$createdColumn   = $date = Factory::getDate()->toSql();;

		$table->store();

	    // Get the new item ID of the copy
	    $copyId = $table->id;

		// Store the new item in the workflow associations table
	    $workflowStage = (object) [
		    'item_id'   => $copyId,
		    'stage_id'  => $this->toStageId,
		    'extension' => $context
	    ];

	    $db->insertObject('#__workflow_associations', $workflowStage);

		// Store info about the original and the new copy in the revision table
	    $revisionInfo = (object) [
		    'original_id' => $originalId,
		    'copy_id' => $copyId,
		    'context' => $context,
		    'modified_by' => $this->currentUserId,
		    'modified_time' => $table->$createdColumn
	    ];

	    $db = $this->getDatabase();
	    $db->insertObject('#__revision_copy_original', $revisionInfo);
    }

    /**
     * Put the copy of an item back to the original item
     *
     * @param  string  $context
     * @param  integer $id
     *
     *  @return void
     */
	private function copyReviewToOriginal($context, $copyId):void
    {
        // Get the info about the revised item and the original item
	    $revisionInfo = $this->revisionInfoCopy($context, $copyId);

		// Get the table object
	    list($componentName, $tableName)  = explode('.', $context);
	    $table = $this->getApplication()->bootComponent($componentName)->getMVCFactory()->createTable($tableName);

	    // Get the original item
	    $originalItem = clone $table;
	    $originalItem->load($revisionInfo->original_id);

	    // Get the revised item
	    $copyItem = clone $table;
	    $copyItem->load($copyId);

	    // Copy the id, category, created_by and created from the original item to the revised + store
	    $catColumn       = $table->getColumnAlias('catid');
	    $createdByColumn = $table->getColumnAlias('created_by');
	    $createdColumn   = $table->getColumnAlias('created');

	    $copyItem->id               = $originalItem->id;
	    $copyItem->$catColumn       = $originalItem->$catColumn;
	    $copyItem->$createdByColumn = $originalItem->$createdByColumn;
	    $copyItem->$createdColumn   = $originalItem->$createdColumn;

		$copyItem->store();

	    // Change the history (versions) of the copy to the original
	    // item_id = '<extensionName>.<tableName>.<id>'. Set from copyId to originalId.
	    $originalItemId = $context . '.' . $originalItem->id;
	    $copyItemId     = $context . '.' . $copyId;

	    $db = $this->getDatabase();
	    $query = $db->getQuery(true)
		    ->update($db->quoteName('#__history'))
		    ->set([
			    $db->quoteName('item_id') . ' = :originalItemId'
		    ])
		    ->where($db->quoteName('item_id') . ' = :copyItemId')
		    ->bind(':originalItemId', $originalItemId)
		    ->bind(':copyItemId', $copyItemId);

	    $db->setQuery($query);
		$db->execute();

		// Only last version of original versions should be is_current=1 (a prior one should be set to 0)
	    // Could be done with a CASE statement and a subquery.
	    // --- Now with Joomla's database query in 3 parts:

	    // 1. Set all is_current from this item to 0
	    $query = $db->getQuery(true)
		    ->update($db->quoteName('#__history'))
		    ->set([
			    $db->quoteName('is_current') . ' = 0'
		    ])
		    ->where($db->quoteName('item_id') . ' = :originalItemId')
		    ->bind(':originalItemId', $originalItemId);

	    $db->setQuery($query);
	    $db->execute();

	    // 2. Get the maximum date of this item
	    $query = $db->getQuery(true)
		    ->select('MAX(' . $db->quoteName('save_date'). ')')
		    ->from($db->quoteName('#__history'))
		    ->where($db->quoteName('item_id') . ' = :originalItemId')
		    ->bind(':originalItemId', $originalItemId);

	    $db->setQuery($query);
	    $maxDate = $db->loadResult();

	    // 3. Set the is_current for this item's maximum date on 1
	    $query = $db->getQuery(true)
		    ->update($db->quoteName('#__history'))
		    ->set([
			    $db->quoteName('is_current') . ' = 1'
		    ])
		    ->where($db->quoteName('save_date') . ' = :maxDate')
		    ->where($db->quoteName('item_id') . ' = :originalItemId')
		    ->bind(':maxDate', $maxDate)
		    ->bind(':originalItemId', $originalItemId);

	    $db->setQuery($query);
	    $db->execute();
	    // --- End of update is_current

	    // Delete workflow associations record of this revision
	    $query = $db->getQuery(true)
		    ->delete($db->quoteName('#__workflow_associations'))
		    ->where($db->quoteName('extension') . ' = :context')
		    ->where($db->quoteName('item_id') . ' = :copyId')
		    ->bind(':context', $context)
		    ->bind(':copyId', $copyId);

	    $db->setQuery($query);
	    $db->execute();

	    // Delete the revision info record of this revision
	    $query = $db->getQuery(true)
		    ->delete($db->quoteName('#__revision_copy_original'))
		    ->where($db->quoteName('context') . ' = :context')
		    ->where($db->quoteName('copy_id') . ' = :copyId')
		    ->bind(':context', $context)
		    ->bind(':copyId', $copyId);

	    $db->setQuery($query);
	    $db->execute();

	    // Delete the revision item (load the copy again, for it was set to the new article)
	    $table->load($copyId);
	    $table->delete();
    }

    /**
     * Check if the current plugin should execute workflow related activities
     *
     * @param   string  $context
     *
     * @return   boolean
     */
    protected function isSupported($context):bool
    {
        if (!$this->checkAllowedAndForbiddenlist($context)) {
            return false;
        }

        $parts = explode('.', $context);

        // We need at least the extension + view for loading the table fields
        if (\count($parts) < 2) {
            return false;
        }

        $component = $this->getApplication()->bootComponent($parts[0]);

        if (!$component instanceof WorkflowServiceInterface || !$component->isWorkflowActive($context)) {
            return false;
        }

        return true;
    }
}
